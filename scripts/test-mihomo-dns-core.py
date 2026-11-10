"""Controlled Mihomo DNS/egress test. Requires the isolated HTTP fixture and PyYAML.
Usage: python scripts/test-mihomo-dns-core.py PATH_TO_MIHOMO
No production data or public DNS queries are used; representative rule-set fixtures.
"""
import json, pathlib, select, socket, socketserver, struct, subprocess, sys, tempfile, threading, time, urllib.request, urllib.parse
import yaml
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
BASE = 'http://127.0.0.1:18891'
def get(path):
    return urllib.request.urlopen(BASE + path).read()
settings = json.loads(get('/admin/setting/dns/data'))['settings']
if not any(p['id'] == 'custom' for p in settings['providers']):
    settings['providers'].append({'id':'custom','name':'Custom fixture','enabled':True,'servers':['https://9.9.9.9/dns-query'],'egress':'DIRECT'})
    settings['policies'].append({'id':'custom','name':'Custom fixture','enabled':True,'priority':1,'match':{'type':'domain','value':'example.com'},'providerId':'custom'})
ports = {'google': 18901, 'cloudflare': 18902, 'ali': 18903, 'tencent': 18904, 'custom': 18905}
hits, proxied = [], []
def read(s, n):
    out = b''
    while len(out) < n:
        chunk = s.recv(n - len(out))
        if not chunk: raise EOFError()
        out += chunk
    return out
class DNS(socketserver.BaseRequestHandler):
    def handle(self):
        try:
            while True:
                size = struct.unpack('!H', read(self.request, 2))[0]
                q = read(self.request, size)
                hits.append(self.server.server_address[1])
                # 返回一个 A 记录，并保留原始问题内容。
                answer = q[:2] + b'\x81\x80' + q[4:6] + b'\x00\x01\x00\x00\x00\x00' + q[12:] + b'\xc0\x0c\x00\x01\x00\x01\x00\x00\x00\x00\x00\x04' + (bytes([127,0,0,1]) if b'gator' in q else bytes([203,0,113,self.server.server_address[1]-18900]))
                self.request.sendall(struct.pack('!H',len(answer))+answer)
        except (EOFError, OSError): pass
class Socks(socketserver.BaseRequestHandler):
    def handle(self):
        try:
            head = read(self.request,2);read(self.request,head[1]);self.request.sendall(b'\x05\x00')
            head = read(self.request,4)
            host = socket.inet_ntoa(read(self.request,4)) if head[3]==1 else read(self.request,read(self.request,1)[0]).decode()
            port = struct.unpack('!H',read(self.request,2))[0];proxied.append(port)
            with socket.create_connection((host,port),3) as peer:
                self.request.sendall(b'\x05\x00\x00\x01\x7f\x00\x00\x01\x00\x00')
                while True:
                    for s in select.select([self.request,peer],[],[],5)[0]:
                        b=s.recv(65536)
                        if not b:return
                        (peer if s is self.request else self.request).sendall(b)
        except (OSError,EOFError):pass
class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address=True
    daemon_threads=True
class Target(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200);self.end_headers();self.wfile.write(b'direct-target-ok')
    def log_message(self,*args):pass
servers=[]
target=ThreadingHTTPServer(('127.0.0.1',18920),Target);servers.append(target)
threading.Thread(target=target.serve_forever,daemon=True).start()
for port, handler in [(p,DNS) for p in ports.values()] + [(18910,Socks)]:
    server=Server(('127.0.0.1',port),handler);servers.append(server)
    threading.Thread(target=server.serve_forever,daemon=True).start()
for p in settings['providers']:
    p['servers']=['tcp://127.0.0.1:'+str(ports[p['id']])]
req=urllib.request.Request(BASE+'/admin/setting/dns',data=urllib.parse.urlencode({'settings':json.dumps(settings)}).encode())
assert json.loads(urllib.request.urlopen(req).read())['ret']==1
profile=yaml.safe_load(get('/sub/fixture-nodes/clash'))
profile['proxies']=[{'name':'Fixture SS','type':'socks5','server':'127.0.0.1','port':18910}]
profile['external-controller']='127.0.0.1:18913';profile['secret']='fixture-only';profile['log-level']='debug';profile['mixed-port']=18911;profile['dns']['listen']='127.0.0.1:18912';profile['dns']['enhanced-mode']='redir-host'
fixtures={'google':['+.google.com','+.gmail.com'],'youtube':['+.youtube.com'],'google-gemini':['+.gemini.google.com'],'cn-domain':['+.baidu.com','+.gator.volces.com'],'private-domain':['+.lan']}
for key in profile['rule-providers']:
    old=profile['rule-providers'][key]
    profile['rule-providers'][key]={'type':'inline','behavior':old['behavior'],'payload':fixtures.get(key,['198.51.100.0/24'] if old['behavior']=='ipcidr' else ['+.unused.invalid'])}
with tempfile.TemporaryDirectory(prefix='ef-dns-core-') as tmp:
    config=pathlib.Path(tmp)/'config.yaml';config.write_text(yaml.safe_dump(profile,allow_unicode=True,sort_keys=False),encoding='utf-8')
    log=open(pathlib.Path(tmp)/'core.log','w')
    core=subprocess.Popen([sys.argv[1],'-d',tmp,'-f',str(config)],stdout=log,stderr=log)
    try:
        time.sleep(2)
        for host,port in [('google.com',18901),('youtube.com',18901),('gmail.com',18901),('gemini.google.com',18901),('baidu.com',18903),('random.invalid',18902),('example.com',18905)]:
            q=struct.pack('!HHHHHH',1234,256,1,0,0,0)+b''.join(bytes([len(s)])+s.encode() for s in host.split('.'))+b'\x00\x00\x01\x00\x01'
            with socket.socket(socket.AF_INET,socket.SOCK_DGRAM) as s:
                s.settimeout(5);s.sendto(q,('127.0.0.1',18912));reply=s.recv(2048)
            assert reply[-4:]==bytes([203,0,113,port-18900]),(host,reply)
            assert port in hits,host
            if port in (18901,18902):assert port in proxied,host
            else:assert port not in proxied,host
            print(host, 'provider',port,'egress', 'SOCKS' if port in proxied else 'DIRECT','PASS')
        # Reload with fake-ip: verify both listener transports and retained policy.
        profile['dns']['enhanced-mode']='fake-ip'
        config.write_text(yaml.safe_dump(profile,allow_unicode=True,sort_keys=False),encoding='utf-8')
        reload_req=urllib.request.Request('http://127.0.0.1:18913/configs?force=true',method='PUT',headers={'Authorization':'Bearer fixture-only','Content-Type':'application/json'},data=json.dumps({'path':str(config)}).encode())
        assert urllib.request.urlopen(reload_req).status==204
        host='gator.volces.com'
        q=struct.pack('!HHHHHH',1235,256,1,0,0,0)+b''.join(bytes([len(s)])+s.encode() for s in host.split('.'))+b'\x00\x00\x01\x00\x01'
        for transport in ('udp','tcp'):
            with socket.socket(socket.AF_INET,socket.SOCK_DGRAM if transport=='udp' else socket.SOCK_STREAM) as s:
                s.settimeout(5)
                if transport=='udp':s.sendto(q,('127.0.0.1',18912));reply=s.recv(2048)
                else:s.connect(('127.0.0.1',18912));s.sendall(struct.pack('!H',len(q))+q);reply=read(s,struct.unpack('!H',read(s,2))[0])
            assert reply[-4:-2]==bytes([198,18]),reply
            print('reload fake-ip',transport,socket.inet_ntoa(reply[-4:]),'PASS')
        before=len(hits)
        with socket.create_connection(('127.0.0.1',18911),5) as client:
            client.sendall(b'CONNECT gator.volces.com:18920 HTTP/1.1\r\nHost: gator.volces.com:18920\r\n\r\n')
            header=client.recv(4096);assert b'200' in header,header
            client.sendall(b'GET / HTTP/1.1\r\nHost: gator.volces.com\r\nConnection: close\r\n\r\n')
            response=b''
            while True:
                chunk=client.recv(4096)
                if not chunk:break
                response+=chunk
            assert b'direct-target-ok' in response,response
        assert 18903 in hits[before:],hits[before:]
        print('gator fake-ip then DIRECT re-resolution via cn policy and real HTTP target PASS')

    finally:
        core.terminate();core.wait(timeout=10);log.close()
for server in servers:server.shutdown();server.server_close()
