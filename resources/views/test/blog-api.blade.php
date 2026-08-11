<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CafeON 블로그 API 테스트</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f1eb;color:#2f2924;font-family:Arial,"Malgun Gothic",sans-serif}.wrap{max-width:1250px;margin:auto;padding:26px}.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.head a{color:#825234}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.panel{background:#fff;border:1px solid #dfd6cc;border-radius:14px;padding:19px;box-shadow:0 5px 17px #4932220c}.wide{grid-column:1/-1}h1{margin:0;font-size:24px}h2{font-size:17px;margin:0 0 14px}label{display:block;font-size:12px;color:#71665e;margin:9px 0 5px}input,select,textarea{width:100%;padding:10px;border:1px solid #d8cec4;border-radius:8px;background:#fff}textarea{min-height:100px;resize:vertical}.row{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}button{border:0;border-radius:8px;padding:10px 14px;background:#8c5938;color:#fff;cursor:pointer}button.alt{background:#5f6d58}button.danger{background:#a4433b}.status{padding:11px 13px;border-radius:9px;background:#eee9e3;margin-bottom:16px;font-size:14px}.ok{background:#e4f2e9;color:#21653e}.error{background:#f9e5e3;color:#8c2f28}.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.card{border:1px solid #e5ddd4;border-radius:10px;padding:13px;background:#fdfcfb}.card h3{font-size:15px;margin:0 0 7px}.meta{font-size:12px;color:#756a62}.selected{outline:2px solid #9a613c}pre{margin:0;max-height:320px;overflow:auto;background:#28231f;color:#efe8df;padding:14px;border-radius:9px;font-size:12px}.hint{font-size:12px;color:#766c64;margin-top:8px}@media(max-width:850px){.grid{grid-template-columns:1fr}.wide{grid-column:auto}.cards{grid-template-columns:1fr}.row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="head"><div><h1>CafeON 블로그 API 테스트</h1><div class="hint">Blade View → JavaScript fetch → Laravel API → MySQL</div></div><a href="{{ route('test.mvc.index') }}">MVC 홈</a></div>
    <div id="status" class="status">먼저 관리자 로그인을 실행하세요.</div>
    <div class="grid">
        <section class="panel">
            <h2>1. 관리자 로그인</h2>
            <label>이메일</label><input id="email" value="admin@cafeon.test">
            <label>비밀번호</label><input id="password" type="password" value="password1234">
            <div class="actions"><button onclick="login()">로그인·토큰 받기</button><button class="alt" onclick="logout()">토큰 지우기</button></div>
        </section>
        <section class="panel">
            <h2>2. 매장과 검색 조건</h2>
            <label>매장</label><select id="store">@foreach($stores as $store)<option value="{{ $store->id }}">#{{ $store->id }} {{ $store->name }}</option>@endforeach</select>
            <div class="row"><div><label>검색어</label><input id="keyword" placeholder="제목·본문 검색"></div><div><label>태그 slug</label><input id="tagFilter" placeholder="event"></div></div>
            <div class="actions"><button onclick="loadPosts()">게시글 조회</button><button class="alt" onclick="loadTaxonomies()">카테고리·태그 조회</button></div>
        </section>
        <section class="panel">
            <h2>3. 게시글 등록·수정</h2>
            <input id="postId" type="hidden">
            <div class="row"><div><label>카테고리</label><select id="category"></select></div><div><label>상태</label><select id="postStatus"><option>PUBLISHED</option><option>DRAFT</option><option>SCHEDULED</option></select></div></div>
            <label>제목</label><input id="title" value="API 테스트 게시글">
            <label>본문</label><textarea id="content">View 화면에서 등록한 테스트 게시글입니다.</textarea>
            <label>태그(여러 개 선택 가능)</label><select id="tags" multiple size="4"></select>
            <div class="actions"><button onclick="createPost()">새 게시글 등록</button><button class="alt" onclick="updatePost()">선택 게시글 수정</button><button class="danger" onclick="deletePost()">선택 게시글 삭제</button></div>
        </section>
        <section class="panel">
            <h2>4. 카테고리·태그 생성</h2>
            <div class="row"><div><label>카테고리 이름</label><input id="categoryName" value="테스트 카테고리"></div><div><label>카테고리 slug</label><input id="categorySlug" value="test-category"></div></div>
            <div class="actions"><button onclick="createCategory()">카테고리 생성</button></div>
            <div class="row"><div><label>태그 이름</label><input id="tagName" value="테스트 태그"></div><div><label>태그 slug</label><input id="tagSlug" value="test-tag"></div></div>
            <div class="actions"><button onclick="createTag()">태그 생성</button></div>
        </section>
        <section class="panel wide">
            <h2>5. 게시글 결과 — 카드를 클릭하면 수정·삭제 대상으로 선택됩니다</h2>
            <div id="posts" class="cards"><div class="hint">게시글 조회 버튼을 누르세요.</div></div>
        </section>
        <section class="panel wide"><h2>최근 API 응답</h2><pre id="output">아직 요청이 없습니다.</pre></section>
    </div>
</div>
<script>
let token=localStorage.getItem('cafeon_test_token')||'';
const el=id=>document.getElementById(id);
function message(text,ok=true){el('status').className='status '+(ok?'ok':'error');el('status').textContent=text}
async function api(path,options={}){const headers={Accept:'application/json',...(options.body instanceof FormData?{}:{'Content-Type':'application/json'}),...(token?{Authorization:`Bearer ${token}`}:{})};const response=await fetch(path,{...options,headers:{...headers,...options.headers}});let data=null;try{data=await response.json()}catch{}el('output').textContent=JSON.stringify({status:response.status,data},null,2);if(!response.ok)throw new Error(data?.message||`HTTP ${response.status}`);return data}
async function run(fn){try{await fn();message('요청 성공')}catch(e){message(e.message,false)}}
async function login(){run(async()=>{const data=await api('/api/auth/login',{method:'POST',body:JSON.stringify({email:el('email').value,password:el('password').value})});token=data.token;localStorage.setItem('cafeon_test_token',token);message('로그인 성공 — 보호 API를 테스트할 수 있습니다.');await loadTaxonomies()})}
function logout(){token='';localStorage.removeItem('cafeon_test_token');message('저장된 토큰을 지웠습니다.')}
async function loadTaxonomies(){return run(async()=>{const id=el('store').value;const [categories,tags]=await Promise.all([api(`/api/stores/${id}/post-categories`),api(`/api/stores/${id}/tags`)]);el('category').innerHTML=categories.map(v=>`<option value="${v.id}">${v.name}</option>`).join('');el('tags').innerHTML=tags.map(v=>`<option value="${v.id}">${v.name}</option>`).join('')})}
async function loadPosts(){return run(async()=>{const params=new URLSearchParams({store_id:el('store').value});if(el('keyword').value)params.set('keyword',el('keyword').value);if(el('tagFilter').value)params.set('tag',el('tagFilter').value);const result=await api('/api/posts?'+params);el('posts').innerHTML=result.data.length?result.data.map(post=>`<article class="card" data-id="${post.id}" onclick='selectPost(${JSON.stringify(JSON.stringify({id:post.id,title:post.title,content:post.content,status:post.status,category_id:post.category_id}))})'><h3>${escapeHtml(post.title)}</h3><div class="meta">#${post.id} · ${post.status} · 조회 ${post.view_count}</div></article>`).join(''):'<div class="hint">조회된 게시글이 없습니다.</div>'})}
function selectPost(raw){const post=JSON.parse(raw);el('postId').value=post.id;el('title').value=post.title;el('content').value=post.content;el('postStatus').value=post.status;el('category').value=post.category_id;document.querySelectorAll('.card').forEach(v=>v.classList.toggle('selected',v.dataset.id==post.id));message(`#${post.id} 게시글을 선택했습니다.`)}
function postPayload(){return {store_id:Number(el('store').value),category_id:Number(el('category').value),title:el('title').value,content:el('content').value,status:el('postStatus').value,tag_ids:[...el('tags').selectedOptions].map(v=>Number(v.value))}}
async function createPost(){run(async()=>{await api('/api/posts',{method:'POST',body:JSON.stringify(postPayload())});await loadPosts()})}
async function updatePost(){run(async()=>{if(!el('postId').value)throw new Error('게시글 카드를 먼저 선택하세요.');const data=postPayload();delete data.store_id;await api(`/api/posts/${el('postId').value}`,{method:'PUT',body:JSON.stringify(data)});await loadPosts()})}
async function deletePost(){run(async()=>{if(!el('postId').value)throw new Error('게시글 카드를 먼저 선택하세요.');if(!confirm('선택 게시글을 삭제할까요?'))return;await api(`/api/posts/${el('postId').value}`,{method:'DELETE'});el('postId').value='';await loadPosts()})}
async function createCategory(){run(async()=>{await api(`/api/stores/${el('store').value}/post-categories`,{method:'POST',body:JSON.stringify({name:el('categoryName').value,slug:el('categorySlug').value})});await loadTaxonomies()})}
async function createTag(){run(async()=>{await api(`/api/stores/${el('store').value}/tags`,{method:'POST',body:JSON.stringify({name:el('tagName').value,slug:el('tagSlug').value})});await loadTaxonomies()})}
function escapeHtml(value){const div=document.createElement('div');div.textContent=value;return div.innerHTML}
if(token)message('저장된 로그인 토큰이 있습니다. 바로 테스트할 수 있습니다.');
loadTaxonomies();loadPosts();
</script>
</body>
</html>
