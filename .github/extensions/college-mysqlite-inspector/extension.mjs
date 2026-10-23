import { createServer } from "node:http";
import { mkdir, readFile, rename, writeFile } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import os from "node:os";
import { DatabaseSync } from "node:sqlite";
import { CanvasError, createCanvas, joinSession } from "@github/copilot-sdk/extension";

const CANVAS_ID = "college-mysqlite-inspector";
const DATABASE_PATH = resolve(process.cwd(), "database/database.sqlite");
const DATA_DIR = join(process.env.COPILOT_HOME || join(os.homedir(), ".copilot"), "extension-data", CANVAS_ID);
const STATE_PATH = join(DATA_DIR, `${Buffer.from(DATABASE_PATH).toString("hex")}.json`);
const servers = new Map();
let session;

const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
}[char]));

const readOnlyQuery = (value) => {
    const query = String(value ?? "").trim().replace(/;+\s*$/, "");
    if (!query || query.length > 5000 || /(?:insert|update|delete|replace|drop|alter|create|attach|detach|vacuum|reindex|pragma\s+[^(\s=]+\s*=)/i.test(query) || !/^(select|with|explain|pragma)\b/i.test(query)) {
        throw new CanvasError("invalid_query", "Only read-only SELECT, WITH, EXPLAIN, and inspection PRAGMA queries are allowed.");
    }
    return query;
};

function database() {
    try {
        return new DatabaseSync(DATABASE_PATH, { readOnly: true });
    } catch (error) {
        throw new CanvasError("database_unavailable", `Could not open ${DATABASE_PATH}: ${error.message}`);
    }
}

function tables() {
    const db = database();
    try {
        return db.prepare("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY name").all();
    } finally {
        db.close();
    }
}

function tableDetails(name) {
    if (!/^[A-Za-z_][A-Za-z0-9_]*$/.test(String(name ?? ""))) {
        throw new CanvasError("invalid_table", "Table names may contain only letters, numbers, and underscores.");
    }
    const db = database();
    try {
        const quoted = `"${name.replaceAll('"', '""')}"`;
        const columns = db.prepare(`PRAGMA table_info(${quoted})`).all();
        if (!columns.length) throw new CanvasError("not_found", `No table or view named "${name}".`);
        const rows = db.prepare(`SELECT * FROM ${quoted} LIMIT 100`).all();
        return { name, columns, rows };
    } catch (error) {
        if (error instanceof CanvasError) throw error;
        throw new CanvasError("database_query_failed", error.message);
    } finally {
        db.close();
    }
}

function query(sql) {
    const db = database();
    try {
        const statement = db.prepare(readOnlyQuery(sql));
        return { columns: statement.columns().map((column) => column.name), rows: statement.all().slice(0, 200) };
    } catch (error) {
        if (error instanceof CanvasError) throw error;
        throw new CanvasError("database_query_failed", error.message);
    } finally {
        db.close();
    }
}

async function loadState() {
    try {
        const parsed = JSON.parse(await readFile(STATE_PATH, "utf8"));
        if (parsed.format !== CANVAS_ID || parsed.version !== 1 || parsed.databasePath !== DATABASE_PATH || typeof parsed.selectedTable !== "string" || typeof parsed.query !== "string") {
            throw new Error("unrecognized state format");
        }
        return parsed;
    } catch (error) {
        if (error.code === "ENOENT") return { format: CANVAS_ID, version: 1, databasePath: DATABASE_PATH, selectedTable: "", query: "SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY name" };
        throw new CanvasError("state_corrupt", `Stored inspector state could not be read: ${error.message}`);
    }
}

async function saveState(state) {
    try {
        await mkdir(DATA_DIR, { recursive: true });
        const temp = join(DATA_DIR, `.${Date.now()}-${process.pid}.tmp`);
        await writeFile(temp, JSON.stringify({ format: CANVAS_ID, version: 1, databasePath: DATABASE_PATH, ...state }), { mode: 0o600 });
        await rename(temp, STATE_PATH);
    } catch (error) {
        throw new CanvasError("state_unavailable", `Inspector state could not be saved: ${error.message}`);
    }
}

function page(instanceId) {
    return `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Database Inspector</title><style>
:root{color-scheme:dark;font:14px system-ui;background:#111827;color:#e5e7eb}body{margin:0;padding:20px;max-width:1400px;margin-inline:auto}
header{display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:1px solid #374151;padding-bottom:14px}
main{display:grid;grid-template-columns:250px 1fr;gap:20px;margin-top:20px}.panel{background:#1f2937;border:1px solid #374151;border-radius:8px;padding:14px}
button{background:#2563eb;color:white;border:0;border-radius:5px;padding:8px 12px;cursor:pointer}button:focus,input:focus,textarea:focus{outline:2px solid #93c5fd;outline-offset:2px}
input,textarea{box-sizing:border-box;width:100%;background:#111827;color:#e5e7eb;border:1px solid #4b5563;border-radius:5px;padding:9px}textarea{min-height:90px;font-family:ui-monospace,monospace}
ul{list-style:none;padding:0;margin:10px 0}li{margin:4px 0}li button{width:100%;text-align:left;background:transparent}li button:hover{background:#374151}
.toolbar{display:flex;gap:8px;align-items:center;margin-bottom:12px}.toolbar input{flex:1}.status{color:#9ca3af;min-height:22px} .table-wrap{overflow:auto;max-height:60vh}table{border-collapse:collapse;width:100%;font-size:13px}th,td{padding:7px 9px;border:1px solid #4b5563;text-align:left;vertical-align:top;white-space:nowrap}th{background:#374151;position:sticky;top:0}
@media(max-width:700px){main{grid-template-columns:1fr}}
</style></head><body><header><div><h1>SQLite Database Inspector</h1><div class="status">Read-only view of ${esc(DATABASE_PATH)}</div></div><button id="refresh">Refresh schema</button></header>
<main><aside class="panel"><h2>Tables and views</h2><ul id="tables" aria-live="polite"></ul></aside><section class="panel"><div class="toolbar"><input id="table" aria-label="Table name" placeholder="Choose a table"><button id="inspect">Inspect</button></div>
<label for="query">Read-only SQL query</label><textarea id="query" spellcheck="false">SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') ORDER BY name</textarea><div class="toolbar"><button id="run">Run query</button><span id="status" class="status" role="status"></span></div><div id="result" class="table-wrap"></div></section></main>
<script>
const $=id=>document.getElementById(id), post=async(path,body)=>{const r=await fetch(path,{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(body||{})});const d=await r.json();if(!r.ok)throw Error(d.error||'Request failed');return d};
function render(data){if(!data.columns?.length){$('result').textContent='No rows returned.';return}const t=document.createElement('table'),h=document.createElement('tr');data.columns.forEach(c=>{const e=document.createElement('th');e.textContent=c;h.append(e)});t.append(h);data.rows.forEach(row=>{const tr=document.createElement('tr');data.columns.forEach(c=>{const td=document.createElement('td');td.textContent=row[c]===null?'NULL':typeof row[c]==='object'?JSON.stringify(row[c]):String(row[c]);tr.append(td)});t.append(tr)});$('result').replaceChildren(t)}
async function load(){try{const d=await (await fetch('/api/tables')).json();$('tables').replaceChildren(...d.map(x=>{const li=document.createElement('li'),b=document.createElement('button');b.textContent=x.name;b.onclick=()=>{$('table').value=x.name;inspect()};li.append(b);return li}))}catch(e){$('status').textContent=e.message}}
async function inspect(){try{const d=await post('/api/inspect',{name:$('table').value});render({columns:d.columns.map(x=>x.name),rows:d.rows});$('status').textContent=d.rows.length+' row(s) shown';}catch(e){$('status').textContent=e.message}}
async function run(){try{const d=await post('/api/query',{sql:$('query').value});render(d);$('status').textContent=d.rows.length+' row(s) shown';}catch(e){$('status').textContent=e.message}}
$('refresh').onclick=load;$('inspect').onclick=inspect;$('run').onclick=run;load();run();
</script></body></html>`;
}

async function startServer(instanceId) {
    const server = createServer(async (request, response) => {
        try {
            const url = new URL(request.url, "http://127.0.0.1");
            if (request.method === "GET" && url.pathname === "/") return response.end(page(instanceId));
            if (request.method === "GET" && url.pathname === "/api/tables") return response.end(JSON.stringify(tables()));
            if (request.method === "POST" && (url.pathname === "/api/query" || url.pathname === "/api/inspect")) {
                const body = JSON.parse(await new Promise((resolveBody, reject) => { let text = ""; request.on("data", (chunk) => { text += chunk; if (text.length > 10000) reject(new Error("Request too large")); }); request.on("end", () => resolveBody(text ? JSON.parse(text) : {})); request.on("error", reject); }));
                const result = url.pathname === "/api/query" ? query(body.sql) : tableDetails(body.name);
                await saveState({ selectedTable: body.name || "", query: body.sql || "" });
                response.setHeader("content-type", "application/json"); return response.end(JSON.stringify(result));
            }
            response.statusCode = 404; return response.end("Not found");
        } catch (error) {
            response.statusCode = error instanceof CanvasError ? 400 : 500;
            response.setHeader("content-type", "application/json"); return response.end(JSON.stringify({ error: error.message }));
        }
    });
    await new Promise((resolveListen, reject) => { server.once("error", reject); server.listen(0, "127.0.0.1", resolveListen); });
    return { server, url: `http://127.0.0.1:${server.address().port}/` };
}

const canvas = createCanvas({
    id: CANVAS_ID,
    displayName: "College database inspector",
    description: "Browse the college SQLite schema, inspect tables, and run safe read-only queries.",
    actions: [
        { name: "list_tables", description: "List all database tables and views.", handler: () => tables() },
        { name: "inspect_table", description: "Return columns and up to 100 rows from a table or view.", inputSchema: { type: "object", required: ["name"], properties: { name: { type: "string" } }, additionalProperties: false }, handler: async (ctx) => { const result = tableDetails(ctx.input.name); await saveState({ selectedTable: ctx.input.name, query: "" }); return result; } },
        { name: "run_query", description: "Run a read-only SQL query and return up to 200 rows.", inputSchema: { type: "object", required: ["sql"], properties: { sql: { type: "string", maxLength: 5000 } }, additionalProperties: false }, handler: async (ctx) => { const result = query(ctx.input.sql); await saveState({ selectedTable: "", query: ctx.input.sql }); return result; } },
    ],
    open: async (ctx) => {
        await loadState();
        let entry = servers.get(ctx.instanceId);
        if (!entry) { entry = await startServer(ctx.instanceId); servers.set(ctx.instanceId, entry); }
        return { title: "College database inspector", status: `${tables().length} tables/views`, url: entry.url };
    },
    onClose: async (ctx) => {
        const entry = servers.get(ctx.instanceId);
        if (entry) { servers.delete(ctx.instanceId); await new Promise((resolveClose) => entry.server.close(resolveClose)); }
    },
});

session = await joinSession({ canvases: [canvas] });
session.log(`Database inspector ready for ${DATABASE_PATH}`, { level: "info" });
