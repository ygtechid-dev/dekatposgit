<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SalePro Multi-Store Cloner</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--bd:#334155;--tx:#e2e8f0;--mut:#94a3b8;--acc:#3b82f6;--ok:#22c55e;--err:#ef4444}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;padding:24px}
.wrap{max-width:900px;margin:0 auto}
h1{font-size:1.4rem;margin-bottom:4px}
.sub{color:var(--mut);font-size:.9rem;margin-bottom:24px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:20px;margin-bottom:16px}
.card h2{font-size:1rem;margin-bottom:14px;color:var(--acc)}
label{display:block;font-size:.85rem;color:var(--mut);margin-bottom:4px;margin-top:10px}
input{width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--bd);background:var(--bg);color:var(--tx);font-size:.9rem}
input:focus{outline:2px solid var(--acc);border-color:transparent}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
button{padding:10px 18px;border-radius:8px;border:0;font-weight:600;cursor:pointer;font-size:.9rem;margin-top:12px}
button:disabled{opacity:.6;cursor:not-allowed}
.btn-p{background:var(--acc);color:#fff}.btn-p:hover{filter:brightness(1.1)}
.btn-d{background:var(--err);color:#fff;font-size:.8rem;padding:6px 12px}
.msg{margin-top:14px;padding:12px;border-radius:8px;font-size:.85rem;display:none;white-space:pre-wrap;line-height:1.5;max-height:340px;overflow:auto}
.msg.ok{display:block;background:rgba(34,197,94,.12);border:1px solid var(--ok);color:var(--ok)}
.msg.err{display:block;background:rgba(239,68,68,.12);border:1px solid var(--err);color:var(--err)}
table{width:100%;border-collapse:collapse;font-size:.85rem;margin-top:8px}
th,td{text-align:left;padding:9px 8px;border-bottom:1px solid var(--bd);vertical-align:middle}
th{color:var(--mut);font-weight:600}
a.store-link{color:var(--acc);text-decoration:none}
.hint{font-size:.78rem;color:var(--mut);margin-top:4px}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.72rem;background:rgba(34,197,94,.15);color:var(--ok);border:1px solid var(--ok)}
/* loading overlay */
.overlay{position:fixed;inset:0;background:rgba(15,23,42,.78);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;z-index:999;padding:24px;text-align:center}
.overlay.show{display:flex}
.spinner{width:52px;height:52px;border:4px solid rgba(255,255,255,.18);border-top-color:var(--acc);border-radius:50%;animation:spin .9s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
#loadingText{font-size:.95rem;color:var(--tx)}
#loadingSub{font-size:.8rem;color:var(--mut);max-width:420px}
</style>
</head>
<body>
<?php
// Hitung toko dari marker
$allStores = [];
foreach (glob(CLONE_ROOT . '/*/.salepro-store') ?: [] as $m) {
    $allStores[] = basename(dirname($m));
}
$count = count($allStores);
$atLimit = $count >= MAX_STORES;
?>
<div class="wrap">
<h1>SalePro Multi-Store Cloner</h1>
<p class="sub">VPS 150.109.22.82 &middot; <?= ROOT_DOMAIN ?> &middot; <b><?= $count ?>/<?= MAX_STORES ?></b> toko terpakai</p>

<!-- CREATE -->
<div class="card">
<h2>Buat Toko Baru <?= $atLimit ? '<span style="color:var(--err)">(limit tercapai)</span>' : '' ?></h2>
<form method="post" id="createForm" <?= $atLimit ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
<input type="hidden" name="action" value="create">
<div class="row">
<div>
<label>Nama Toko</label>
<input type="text" name="store_name" placeholder="Barokah Mart" required>
</div>
<div>
<label>Subdomain</label>
<input type="text" name="subdomain" placeholder="barokahmart" pattern="[a-z0-9-]{2,30}" required>
<div class="hint">Akses: https://<b style="color:var(--tx)">subdomain</b>.<?= ROOT_DOMAIN ?></div>
</div>
</div>
<div class="row">
<div>
<label>Email Admin</label>
<input type="email" name="admin_email" placeholder="admin@barokahmart.id">
<div class="hint">Kosongkan = pakai admin default (demo@mail.com)</div>
</div>
<div>
<label>Admin Password</label>
<input type="password" name="admin_pass" placeholder="password admin">
</div>
</div>
<button class="btn-p" type="submit" id="createBtn" <?= $atLimit ? 'disabled' : '' ?>>Buat Toko</button>
<?php if ($atLimit): ?>
<p style="color:var(--err);font-size:.8rem;margin-top:8px">Limit <?= MAX_STORES ?> toko tercapai. Hapus toko lama dulu.</p>
<?php endif; ?>
</form>
<div class="msg" id="msg1"></div>
<div id="reloadWrap" style="display:none;margin-top:12px">
<button class="btn-p" type="button" onclick="location.reload()">Muat ulang untuk lihat daftar terbaru</button>
</div>
</div>

<!-- LIST -->
<div class="card">
<h2>Daftar Toko</h2>
<?php if (!empty($result['stores'])): ?>
<table>
<thead>
<tr><th>Toko</th><th>Subdomain</th><th>Database</th><th>URL</th><th>Aksi</th></tr>
</thead>
<tbody>
<?php foreach ($result['stores'] as $s): ?>
<tr>
<td><b><?= htmlspecialchars($s['name'] ?? $s['subdomain']) ?></b></td>
<td><span class="badge"><?= htmlspecialchars($s['subdomain']) ?></span></td>
<td style="font-family:monospace;font-size:.8rem"><?= htmlspecialchars($s['db']) ?></td>
<td><a class="store-link" href="https://<?= htmlspecialchars($s['subdomain']) ?>.<?= ROOT_DOMAIN ?>" target="_blank" rel="noopener">https://<?= htmlspecialchars($s['subdomain']) ?>.<?= ROOT_DOMAIN ?></a></td>
<td style="white-space:nowrap">
<button class="btn-p" style="margin:0;padding:6px 10px;font-size:.78rem" onclick="repairStore('<?= htmlspecialchars($s['subdomain']) ?>')">Perbaiki</button>
<button class="btn-p" style="margin:0;padding:6px 10px;font-size:.78rem;background:#475569" onclick="fixEnv('<?= htmlspecialchars($s['subdomain']) ?>')">Fix Env</button>
<button class="btn-p" style="margin:0;padding:6px 10px;font-size:.78rem;background:#475569" onclick="diagStore('<?= htmlspecialchars($s['subdomain']) ?>')">Diagnosa</button>
<button class="btn-d" onclick="quickRemove('<?= htmlspecialchars($s['subdomain']) ?>')">Hapus</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p style="color:var(--mut);font-size:.85rem">Belum ada toko.</p>
<?php endif; ?>
<div class="msg" id="msg3"></div>
</div>
</div>

<!-- loading overlay -->
<div class="overlay" id="loading">
<div class="spinner"></div>
<div id="loadingText">Memproses...</div>
<div id="loadingSub">Mohon tunggu dan jangan tutup halaman ini.</div>
</div>

<script>
function showMsg(id,text,ok){const el=document.getElementById(id);el.className='msg '+(ok?'ok':'err');el.textContent=text;el.scrollIntoView({block:'nearest',behavior:'smooth'});}
function post(action, data){
  const fd=new FormData();
  fd.append('action', action);
  for(const k in data) fd.append(k, data[k]);
  return fetch('index.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json());
}

let loadTimer=null;
function startLoading(msgs){
  const ov=document.getElementById('loading');
  const txt=document.getElementById('loadingText');
  let i=0; txt.textContent=msgs[0];
  ov.classList.add('show');
  clearInterval(loadTimer);
  loadTimer=setInterval(()=>{ i=(i+1)%msgs.length; txt.textContent=msgs[i]; },6000);
}
function stopLoading(){ clearInterval(loadTimer); loadTimer=null; document.getElementById('loading').classList.remove('show'); }

// CREATE
document.getElementById('createForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const f=e.target;
  const name=f.querySelector('[name=store_name]').value.trim();
  const sub=f.querySelector('[name=subdomain]').value.trim().toLowerCase();
  if(!name||!sub) return;
  if(!/^[a-z0-9-]{2,30}$/.test(sub)){ showMsg('msg1','Subdomain hanya huruf kecil, angka, strip (-), 2-30 karakter.',false); return; }
  if(!confirm('Buat toko "'+name+'" di https://'+sub+'.<?= ROOT_DOMAIN ?> ?')) return;

  const btn=document.getElementById('createBtn');
  btn.disabled=true;
  startLoading([
    'Menyiapkan folder toko...',
    'Menyalin kode SalePro (bisa 1-2 menit)...',
    'Membuat database & import data...',
    'Menulis konfigurasi .env & admin...',
    'Menulis konfigurasi Nginx...',
    'Mendaftarkan SSL (background)...',
    'Hampir selesai...'
  ]);
  try{
    const res=await fetch('index.php',{method:'POST',body:new FormData(f),headers:{'X-Requested-With':'XMLHttpRequest'}});
    const j=await res.json();
    stopLoading();
    showMsg('msg1', j.msg + (j.steps?'\n\n'+j.steps.join('\n'):'') + (j.log?'\n\nLOG:\n'+j.log.join('\n'):''), j.ok);
    if(j.ok){
      // jangan auto-reload: biar kredensial DB & hasil tetap terbaca
      document.getElementById('reloadWrap').style.display='block';
      btn.textContent='Toko dibuat';
    } else {
      btn.disabled=false;
    }
  }catch(err){
    stopLoading(); btn.disabled=false;
    showMsg('msg1','Error jaringan/serve: '+err,false);
  }
});

// quick remove from list
function quickRemove(sub){
  if(!confirm('Hapus toko "'+sub+'" (DB + kode + nginx)? Tindakan ini permanen.')) return;
  startLoading(['Menghapus toko '+sub+'...','Membersihkan database...','Menghapus konfigurasi nginx...']);
  post('remove',{subdomain:sub}).then(j=>{
    stopLoading(); showMsg('msg3',j.msg,j.ok);
    if(j.ok) setTimeout(()=>location.reload(),1500);
  }).catch(e=>{stopLoading(); showMsg('msg3','Error: '+e,false);});
}

function repairStore(sub){
  showMsg('msg3','Memperbaiki nginx '+sub+' ...',true);
  post('repair',{subdomain:sub}).then(j=>{
    showMsg('msg3',j.msg,j.ok);
    if(j.ok) setTimeout(()=>location.reload(),1500);
  }).catch(e=>showMsg('msg3','Error: '+e,false));
}
function fixEnv(sub){
  showMsg('msg3','Memperbaiki .env '+sub+' (USER_VERIFIED=1) ...',true);
  post('fixenv',{subdomain:sub}).then(j=>showMsg('msg3',j.msg,j.ok))
    .catch(e=>showMsg('msg3','Error: '+e,false));
}
function diagStore(sub){
  showMsg('msg3','Mendiagnosa '+sub+' ...',true);
  post('diag',{subdomain:sub}).then(j=>showMsg('msg3',j.msg,j.ok))
    .catch(e=>showMsg('msg3','Error: '+e,false));
}
</script>
</body>
</html>
