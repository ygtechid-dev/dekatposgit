<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SalePro Multi-Store Cloner</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--bd:#334155;--tx:#e2e8f0;--mut:#94a3b8;--acc:#3b82f6;--ok:#22c55e;--err:#ef4444}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;padding:24px}
.wrap{max-width:820px;margin:0 auto}
h1{font-size:1.4rem;margin-bottom:4px}
.sub{color:var(--mut);font-size:.9rem;margin-bottom:24px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:20px;margin-bottom:16px}
.card h2{font-size:1rem;margin-bottom:14px;color:var(--acc)}
label{display:block;font-size:.85rem;color:var(--mut);margin-bottom:4px;margin-top:10px}
input{width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);color:var(--tx);font-size:.9rem}
input:focus{outline:2px solid var(--acc);border-color:transparent}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
button{padding:10px 18px;border-radius:8px;border:0;font-weight:600;cursor:pointer;font-size:.9rem;margin-top:12px}
.btn-p{background:var(--acc);color:#fff}.btn-p:hover{filter:brightness(1.1)}
.btn-d{background:var(--err);color:#fff;font-size:.8rem;padding:6px 12px}
.msg{margin-top:14px;padding:12px;border-radius:8px;font-size:.85rem;display:none;white-space:pre-wrap;line-height:1.5}
.msg.ok{display:block;background:rgba(34,197,94,.12);border:1px solid var(--ok);color:var(--ok)}
.msg.err{display:block;background:rgba(239,68,68,.12);border:1px solid var(--err);color:var(--err)}
table{width:100%;border-collapse:collapse;font-size:.85rem;margin-top:8px}
th,td{text-align:left;padding:8px;border-bottom:1px solid var(--bd)}
th{color:var(--mut);font-weight:600}
a.store-link{color:var(--acc);text-decoration:none}
.hint{font-size:.78rem;color:var(--mut);margin-top:4px}
</style>
</head>
<body>
<div class="wrap">
<h1>SalePro Multi-Store Cloner</h1>
<p class="sub">VPS 150.109.22.82 &middot; domain <?= ROOT_DOMAIN ?></p>

<!-- CREATE -->
<div class="card">
<h2>Buat Toko Baru</h2>
<form method="post" id="createForm" onsubmit="return doCreate()">
<input type="hidden" name="action" value="create">
<div class="row">
<div>
<label>Nama Toko</label>
<input type="text" name="store_name" placeholder="Barokah Mart" required>
</div>
<div>
<label>Subdomain</label>
<input type="text" name="subdomain" placeholder="barokahmart" required>
<div class="hint">Akses: https://<b style="color:var(--tx)">subdomain</b>.<?= ROOT_DOMAIN ?></div>
</div>
</div>
<div class="row">
<div>
<label>Email Admin</label>
<input type="email" name="admin_email" placeholder="admin@barokahmart.id">
<div class="hint">Kosongkan jika tidak buat admin awal</div>
</div>
<div>
<label>Admin Password</label>
<input type="password" name="admin_pass" placeholder="password admin">
</div>
</div>
<button class="btn-p" type="submit">Buat Toko</button>
</form>
<div class="msg" id="msg1"></div>
</div>

<!-- REMOVE -->
<div class="card">
<h2>Hapus Toko</h2>
<form method="post" id="removeForm" onsubmit="return doRemove(this)">
<input type="hidden" name="action" value="remove">
<div class="row">
<div><label>Subdomain</label><input type="text" name="subdomain" placeholder="barokahmart"></div>
<div><label>&nbsp;</label><button class="btn-d" type="submit">Hapus (DB + kode + nginx)</button></div>
</div>
</form>
<div class="msg" id="msg2"></div>
</div>

<!-- LIST -->
<div class="card">
<h2>Daftar Toko</h2>
<?php if (!empty($result['stores'])): ?>
<table>
<tr><th>Subdomain</th><th>Database</th><th>URL</th><th></th></tr>
<?php foreach ($result['stores'] as $s): ?>
<tr>
<td><?= htmlspecialchars($s['subdomain']) ?></td>
<td><?= htmlspecialchars($s['db']) ?></td>
<td><a class="store-link" href="<?= htmlspecialchars($s['url']) ?>" target="_blank"><?= htmlspecialchars($s['url']) ?></a></td>
<td><button class="btn-d" onclick="quickRemove('<?= $s['subdomain'] ?>')">Hapus</button></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:var(--mut);font-size:.85rem">Belum ada toko.</p>
<?php endif; ?>
</div>
</div>

<script>
function showMsg(id,text,ok){const el=document.getElementById(id);el.className='msg '+(ok?'ok':'err');el.textContent=text;}
function doCreate(){
  const f=document.getElementById('createForm');
  const sub=f.querySelector('[name=subdomain]').value;
  const name=f.querySelector('[name=store_name]').value;
  if(!confirm('Buat toko "'+name+'" di subdomain "'+sub+'"?'))return false;
  return true;
}
function doRemove(form){
  const sub=form.querySelector('[name=subdomain]').value;
  if(!confirm('Hapus store "'+sub+'" (DB+kode+nginx)?'))return false;
  return true;
}
function quickRemove(sub){
  if(!confirm('Hapus store "'+sub+'"?'))return;
  const f=document.getElementById('removeForm');
  f.querySelector('[name=subdomain]').value=sub;
  doRemove(f)&&f.submit();
}
// AJAX create
document.getElementById('createForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd=new FormData(this);
  const res=await fetch('index.php',{method:'POST',body:fd});
  const j=await res.json();
  showMsg('msg1',j.msg+(j.steps?'\n\n'+j.steps.join('\n'):'')+(j.log?'\n\nLOG:\n'+j.log.join('\n'):''),j.ok);
  if(j.ok) setTimeout(()=>location.reload(),1500);
});
document.getElementById('removeForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd=new FormData(this);
  const res=await fetch('index.php',{method:'POST',body:fd});
  const j=await res.json();
  showMsg('msg2',j.msg,j.ok);
  if(j.ok) setTimeout(()=>location.reload(),1000);
});
</script>
</body>
</html>
