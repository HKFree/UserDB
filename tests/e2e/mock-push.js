// Mock push služby prohlížeče pro E2E: ukládá šifrované zprávy, test si je stáhne a dešifruje klíči zařízení
const http = require('http');

const prijato = {};
http.createServer((req, res) => {
    const [, akce, id] = req.url.split('/');
    if (req.method === 'POST' && akce === 'push') {
        const casti = [];
        req.on('data', (c) => casti.push(c));
        req.on('end', () => {
            (prijato[id] ??= []).push(Buffer.concat(casti).toString('base64'));
            res.writeHead(201).end();
        });
    } else if (req.method === 'GET' && akce === 'prijato') {
        res.writeHead(200, { 'Content-Type': 'application/json' }).end(JSON.stringify(prijato[id] || []));
    } else {
        res.writeHead(akce === 'health' ? 200 : 404).end();
    }
}).listen(9999, '0.0.0.0');
