"""Read-only CFW/Mihomo DNS evidence. Prints no subscription URLs or credentials.
Requires PyYAML. Usage: python scripts/audit-mihomo-runtime.py [clash-directory]
"""
import json, pathlib, socket, struct, sys, urllib.request
import yaml
sys.stdout.reconfigure(encoding='utf-8')
p=pathlib.Path(sys.argv[1]) if len(sys.argv)>1 else pathlib.Path.home()/'.config/clash'
c=yaml.safe_load((p/'config.yaml').read_text(encoding='utf-8'))
http=urllib.request.build_opener(urllib.request.ProxyHandler({}))
base='http://'+c['external-controller']
def api(route):
    return json.load(http.open(urllib.request.Request(base+route,headers={'Authorization':'Bearer '+c.get('secret','')}),timeout=8))
print('version',api('/version'))
r=api('/providers/rules').get('providers',{})
print('rulesets',{k:r[k].get('ruleCount') for k in ['cn-domain','private-domain'] if k in r})
for host in ['gator.volces.com','baidu.com','example.com']:
    try:
        a=api('/dns/query?name='+host+'&type=A')
        print('internal DNS',host,[v['data'] for v in a.get('Answer',[]) if v['type']==1])
    except Exception as e:print('internal DNS',host,type(e).__name__)
q=struct.pack('!HHHHHH',4321,256,1,0,0,0)+b''.join(bytes([len(s)])+s.encode() for s in 'gator.volces.com'.split('.'))+b'\x00\x00\x01\x00\x01'
for transport in ['udp','tcp']:
    try:
        with socket.socket(socket.AF_INET,socket.SOCK_DGRAM if transport=='udp' else socket.SOCK_STREAM) as s:
            s.settimeout(2)
            if transport=='udp':s.sendto(q,('127.0.0.1',1053));b=s.recv(4096)
            else:s.connect(('127.0.0.1',1053));s.sendall(struct.pack('!H',len(q))+q);b=s.recv(4096)
            print('listener',transport,'response bytes',len(b))
    except OSError as e:print('listener',transport,type(e).__name__)
# Log exact provenance, bounded samples instead of dumping private traffic logs.
for f in sorted((p/'logs').glob('*.log')):
    counts={};samples={}
    for line in f.open(encoding='utf-8',errors='replace'):
        if 'gator.volces.com --> [0.0.0.0]' not in line:continue
        kind='answer' if ' A from ' in line else 'cache'
        counts[kind]=counts.get(kind,0)+1
        if kind not in samples:samples[kind]=line.strip()
    if counts:
        print('log',f.name,counts)
        for v in samples.values():print(v)
