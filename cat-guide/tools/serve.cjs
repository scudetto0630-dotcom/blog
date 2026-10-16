// Local-only development server. Node's event loop avoids idle-socket stalls in simple HTTPServer.
const http=require('node:http'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'../build'),port=Number(process.env.CAT_GUIDE_PORT||8025);
if(!Number.isInteger(port)||port<1024||port>65535)throw new Error('Invalid CAT_GUIDE_PORT');
const types={'.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.webp':'image/webp','.svg':'image/svg+xml','.xml':'application/xml; charset=utf-8'};
http.createServer((req,res)=>{
 if(!['GET','HEAD'].includes(req.method)){res.writeHead(405,{Allow:'GET, HEAD'});return res.end()}
 let file;try{file=path.resolve(root,'.'+decodeURIComponent(new URL(req.url,'http://localhost').pathname))}catch{res.writeHead(400);return res.end()}
 if(file!==root&&!file.startsWith(root+path.sep)){res.writeHead(403);return res.end()}
 if(fs.existsSync(file)&&fs.statSync(file).isDirectory())file=path.join(file,'index.html');
 if(!types[path.extname(file)]){res.writeHead(404);return res.end()}
 fs.readFile(file,(error,data)=>{if(error){res.writeHead(404);return res.end()}res.writeHead(200,{'Content-Type':types[path.extname(file)],'Cache-Control':'no-store'});res.end(req.method==='HEAD'?undefined:data)})
}).listen(port,'127.0.0.1',()=>console.log(`Cat guide local server ready on 127.0.0.1:${port}`));
