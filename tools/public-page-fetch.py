import urllib.request,sys,json,base64
u=sys.argv[1]
if not u.startswith('https://hirogarushumi.com/'): print(json.dumps({'status':204,'body':''}));sys.exit()
try:
 with urllib.request.urlopen(urllib.request.Request(u,headers={'User-Agent':'HirogaruValidation/1.0 bot'}),timeout=30) as r: print(json.dumps({'status':r.status,'type':r.headers.get('Content-Type','application/octet-stream'),'body':base64.b64encode(r.read()).decode()}))
except Exception: print(json.dumps({'status':502,'body':''}))
