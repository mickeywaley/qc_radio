<?php
// 配置
$chatFile = 'chat_messages.txt';
$usersFile = 'online_users.txt';
$cleanupTime = 300;

function updateOnlineUsers() {
    global $usersFile, $cleanupTime;
    $userId = session_id();
    if(empty($userId)) { session_start(); $userId = session_id(); }
    $currentTime = time();
    $users = array();
    if(file_exists($usersFile)) { $data = file_get_contents($usersFile); $users = unserialize($data); }
    $users[$userId] = $currentTime;
    foreach($users as $id => $time) { if($currentTime - $time > $cleanupTime) unset($users[$id]); }
    file_put_contents($usersFile, serialize($users));
    return count($users);
}

function getOnlineUsersCount() {
    global $usersFile, $cleanupTime;
    if(!file_exists($usersFile)) return 0;
    $currentTime = time();
    $data = file_get_contents($usersFile);
    $users = unserialize($data);
    foreach($users as $id => $time) { if($currentTime - $time > $cleanupTime) unset($users[$id]); }
    return count($users);
}

function handleChatMessage() {
    global $chatFile;
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $imgUrl = isset($_POST['img_url']) ? trim($_POST['img_url']) : '';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '匿名听众';
        $username = htmlspecialchars($username);
        if(!empty($message) || !empty($imgUrl)) {
            $time = date('H:i');
            $entry = array('time' => $time, 'username' => $username, 'message' => htmlspecialchars($message), 'img_url' => $imgUrl);
            $messages = array();
            if(file_exists($chatFile)) { $data = file_get_contents($chatFile); $messages = unserialize($data); }
            if(count($messages) >= 100) array_shift($messages);
            $messages[] = $entry;
            file_put_contents($chatFile, serialize($messages));
            return true;
        }
    }
    return false;
}

function getChatMessages() {
    global $chatFile;
    if(!file_exists($chatFile)) return array();
    $data = file_get_contents($chatFile);
    return unserialize($data);
}

function clearChatMessages() {
    global $chatFile;
    file_put_contents($chatFile, serialize(array()));
    return true;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action']) && $_POST['action'] === 'send_message') { handleChatMessage(); header('Content-Type: application/json'); echo json_encode(array('status' => 'success')); exit; }
    elseif(isset($_POST['action']) && $_POST['action'] === 'get_messages') { $messages = getChatMessages(); header('Content-Type: application/json'); echo json_encode($messages); exit; }
    elseif(isset($_POST['action']) && $_POST['action'] === 'get_online_count') { $count = getOnlineUsersCount(); header('Content-Type: application/json'); echo json_encode(array('count' => $count)); exit; }
    elseif(isset($_POST['action']) && $_POST['action'] === 'clear_chat') { clearChatMessages(); header('Content-Type: application/json'); echo json_encode(array('status' => 'success')); exit; }
}

$onlineCount = updateOnlineUsers();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清晨音乐台 - 在线播放器</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#3B82F6', secondary: '#10B981', accent: '#F59E0B', dark: '#1E293B', light: '#F8FAFC' },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .text-shadow { text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .backdrop-blur { backdrop-filter: blur(8px); }
            .player-gradient { background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); }
            .volume-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; border-radius: 50%; background: #3B82F6; cursor: pointer; }
            .chat-scroll::-webkit-scrollbar { width: 5px; }
            .chat-scroll::-webkit-scrollbar-track { background: #f8fafc; }
            .chat-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
            html, body { overflow: hidden; height: 100%; }
            .img-mask { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 1rem; }
            .img-mask img { max-width: 100%; max-height: 100%; border-radius: 8px; }
            .upload-progress { height: 4px; background: #e5e7eb; border-radius: 2px; margin-top: 4px; display: none; }
            .upload-progress-bar { height: 100%; background: #3B82F6; border-radius: 2px; width: 0%; transition: width 0.15s; }
        }
    </style>
</head>
<body class="bg-gray-100 h-screen font-sans text-gray-800">
    <div class="img-mask" id="imgMask"><img src="" alt="大图预览" id="bigImg"></div>

    <div class="container mx-auto px-4 py-8 max-w-6xl h-full flex flex-col">
        <header class="text-center mb-4 shrink-0">
            <h1 class="text-[clamp(1.5rem,4vw,2.5rem)] font-bold text-primary text-shadow"><i class="fa fa-music mr-2"></i>清晨音乐台</h1>
            <p class="text-gray-600 text-sm">用音乐唤醒美好的一天</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1 overflow-hidden">
            <!-- 左侧播放区 完全原样 -->
            <div class="lg:col-span-2 h-full overflow-y-auto">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="relative h-64 md:h-80 player-gradient">
                        <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('https://picsum.photos/id/1068/800/600');"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white/20 backdrop-blur flex items-center justify-center animate-pulse">
                                <i class="fa fa-headphones text-white text-4xl md:text-5xl"></i>
                            </div>
                        </div>
                        <div class="absolute bottom-4 right-4 bg-black/50 text-white px-3 py-1 rounded-full text-sm backdrop-blur">
                            <i class="fa fa-users mr-1"></i> 在线: <span id="online-count"><?php echo $onlineCount; ?></span>
                        </div>
                    </div>

                    <div class="p-6">
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold mb-1">清晨音乐台直播</h2>
                            <p class="text-gray-500 text-sm">美好的一天从音乐开始</p>
                        </div>
                        
                        <audio id="radio-player" class="w-full" controls>
                            <source src="https://lhttp.qingting.fm/live/4915/64k.mp3" type="audio/mpeg">
                            您的浏览器不支持音频播放
                        
                        
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-medium text-gray-700">音量控制</h3>
                                <span id="volume-percentage" class="bg-primary/10 text-primary px-3 py-1 rounded-full text-sm font-medium">80%</span>
                            </div>
                            
                            <div class="grid grid-cols-5 gap-4 items-center">
                                <button id="volume-down" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-lg transition-all flex items-center justify-center">
                                    <i class="fa fa-volume-down text-gray-700"></i>
                                </button>
                                
                                <input type="range" id="volume-slider" min="0" max="100" value="80" 
                                       class="col-span-3 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer volume-slider">
                                       
                                <button id="volume-up" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-lg transition-all flex items-center justify-center">
                                    <i class="fa fa-volume-up text-gray-700"></i>
                                </button>
                            </div>
                            
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <button id="volume-mute" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-lg transition-all flex items-center justify-center">
                                    <i class="fa fa-volume-off text-gray-700"></i>
                                    <span class="ml-2">静音</span>
                                </button>
                                <button id="fullscreen" class="bg-gray-100 hover:bg-gray-200 p-3 rounded-lg transition-all flex items-center justify-center">
                                    <i class="fa fa-expand text-gray-700"></i>
                                    <span class="ml-2">全屏</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fa fa-info-circle text-primary mr-2"></i> 关于清晨音乐台
                    </h3>
                    <p class="text-gray-700 leading-relaxed">
                        清晨音乐台是一个专注于提供轻松、舒缓音乐的在线广播频道。每天早晨，我们为您精选最适合唤醒心灵的音乐，
                        让美好的旋律伴随您开启全新的一天。无论是经典的轻音乐，还是现代的放松曲目，都能在这里找到。
                    </p>
                    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <i class="fa fa-clock-o text-primary text-xl mb-2"></i>
                            <p class="text-sm">全天候播放</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <i class="fa fa-music text-primary text-xl mb-2"></i>
                            <p class="text-sm">精选曲目</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <i class="fa fa-wifi text-primary text-xl mb-2"></i>
                            <p class="text-sm">高清音质</p>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg text-center">
                            <i class="fa fa-comments text-primary text-xl mb-2"></i>
                            <p class="text-sm">互动交流</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 聊天室 -->
            <div class="lg:col-span-1 h-full overflow-hidden">
                <div class="bg-white rounded-xl shadow-lg h-full flex flex-col">
                    <div class="p-3 border-b border-gray-200 flex justify-between items-center shrink-0">
                        <h3 class="text-base font-semibold flex items-center">
                            <i class="fa fa-comments text-accent mr-2"></i> 听众聊天室
                        </h3>
                        <button id="clear-chat" class="bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded-lg text-xs transition-all">
                            <i class="fa fa-trash mr-1"></i>清屏
                        </button>
                    </div>
                    
                    <div id="chat-messages" class="flex-1 p-3 overflow-y-auto chat-scroll">
                        <div class="text-center text-gray-500 text-sm py-4">欢迎加入聊天室，与其他听众交流</div>
                    </div>
                    
                    <div class="p-3 border-t border-gray-200 shrink-0">
                        <div class="mb-2">
                            <input type="text" id="username" placeholder="请输入您的昵称" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <input type="file" id="image-upload" accept="image/*" class="hidden">
                            <button id="send-image" class="bg-indigo-500 hover:bg-indigo-600 text-white px-2 py-1.5 rounded-lg text-xs transition-all">
                                <i class="fa fa-image"></i>
                            </button>

                            <input type="text" id="message-input" placeholder="输入消息..." 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-primary">
                            
                            <button id="send-message" class="bg-primary hover:bg-primary-600 text-white px-3 py-2 rounded-lg text-sm transition-all">
                                <i class="fa fa-paper-plane"></i>
                            </button>
                        </div>
                        <div class="upload-progress" id="uploadProgress">
                            <div class="upload-progress-bar" id="uploadProgressBar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-3 text-center text-gray-500 text-xs shrink-0">
            <p>© 2023 清晨音乐台</p>
            <a href="https://github.com/mickeywaley/qc_radio" target="_blank" class="text-primary hover:text-primary/80">
                <i class="fa fa-github mr-1"></i>GitHub
            </a>
        </footer>
    </div>

    <script>
        // 图片放大
        const imgMask = document.getElementById('imgMask');
        const bigImg = document.getElementById('bigImg');
        imgMask.addEventListener('click', () => imgMask.style.display = 'none');

        // 播放器控制 完全不动
        const audio = document.getElementById('radio-player');
        const volumeUpBtn = document.getElementById('volume-up');
        const volumeDownBtn = document.getElementById('volume-down');
        const volumeMuteBtn = document.getElementById('volume-mute');
        const volumeSlider = document.getElementById('volume-slider');
        const volumePercentage = document.getElementById('volume-percentage');
        const fullscreenBtn = document.getElementById('fullscreen');
        
        audio.volume = 0.8;
        let lastVolume = 0.8;
        
        function updateVolumeDisplay() {
            const percentage = Math.round(audio.volume * 100);
            volumePercentage.textContent = `${percentage}%`;
            volumeSlider.value = percentage;
            if (audio.muted || percentage === 0) {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-off text-gray-700"></i><span class="ml-2">静音</span>';
            } else if (percentage < 50) {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-down text-gray-700"></i><span class="ml-2">恢复音量</span>';
            } else {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-up text-gray-700"></i><span class="ml-2">静音</span>';
            }
        }
        
        volumeUpBtn.addEventListener('click', () => {
            if (audio.muted) { audio.muted = false; audio.volume = lastVolume; }
            else { audio.volume = Math.min(1, audio.volume + 0.05); }
            updateVolumeDisplay();
        });
        volumeDownBtn.addEventListener('click', () => {
            if (audio.muted) { audio.muted = false; audio.volume = lastVolume; }
            else { audio.volume = Math.max(0, audio.volume - 0.05); }
            updateVolumeDisplay();
        });
        volumeMuteBtn.addEventListener('click', () => {
            if (audio.muted) {
                audio.muted = false; audio.volume = lastVolume;
            } else {
                lastVolume = audio.volume; audio.muted = true;
            }
            updateVolumeDisplay();
        });
        volumeSlider.addEventListener('input', () => {
            const volume = volumeSlider.value / 100;
            audio.volume = volume; audio.muted = false; lastVolume = volume;
            updateVolumeDisplay();
        });
        fullscreenBtn.addEventListener('click', () => {
            const playerContainer = audio.closest('.bg-white');
            if (!document.fullscreenElement) {
                playerContainer.requestFullscreen().catch(err => {});
            } else {
                document.exitFullscreen();
            }
        });

        // ========== 图片压缩核心：自动压缩到300KB ==========
        function compressImage(file, maxSizeKB = 300) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function(e) {
                    const img = new Image();
                    img.src = e.target.result;
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width;
                        let height = img.height;
                        // 等比例缩放
                        const maxSide = 1200;
                        if(width > maxSide || height > maxSide) {
                            if(width > height) {
                                width = maxSide;
                                height = (img.height * maxSide) / img.width;
                            } else {
                                height = maxSide;
                                width = (img.width * maxSide) / img.height;
                            }
                        }
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        // 压缩质量循环逼近300KB
                        let quality = 0.92;
                        function getCompressed() {
                            const dataUrl = canvas.toDataURL('image/jpeg', quality);
                            const sizeKB = Math.round((dataUrl.length - 'data:image/jpeg;base64,'.length) * 3 / 4 / 1024);
                            if(sizeKB > maxSizeKB && quality > 0.5) {
                                quality -= 0.05;
                                getCompressed();
                            } else {
                                resolve(dataUrl);
                            }
                        }
                        getCompressed();
                    }
                }
            });
        }

        // 聊天相关
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const usernameInput = document.getElementById('username');
        const sendMessageBtn = document.getElementById('send-message');
        const onlineCountEl = document.getElementById('online-count');
        const clearChatBtn = document.getElementById('clear-chat');
        const sendImageBtn = document.getElementById('send-image');
        const imageUpload = document.getElementById('image-upload');
        const uploadProgress = document.getElementById('uploadProgress');
        const uploadProgressBar = document.getElementById('uploadProgressBar');

        // 上传图片 压缩+秒显示
        sendImageBtn.addEventListener('click', () => imageUpload.click());
        imageUpload.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if(!file || !file.type.startsWith('image/')) return;
            const username = usernameInput.value.trim() || '匿名听众';

            uploadProgress.style.display = 'block';
            uploadProgressBar.style.width = '0%';

            // 压缩到300KB
            const compressedImg = await compressImage(file);
            
            // 立刻本地渲染，不用等轮询
            addLocalImageMsg(username, compressedImg);

            // 后台静默提交保存
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    uploadProgressBar.style.width = percent + '%';
                }
            };
            xhr.onload = function() {
                uploadProgress.style.display = 'none';
                imageUpload.value = '';
            };
            xhr.send(`action=send_message&username=${encodeURIComponent(username)}&message=&img_url=${encodeURIComponent(compressedImg)}`);
        });

        // 本地直接插入消息，秒加载
        function addLocalImageMsg(name, imgSrc) {
            const nowTime = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            const el = document.createElement('div');
            el.className = 'mb-2 pb-2 border-b border-gray-100';
            el.innerHTML = `
                <div class="flex items-start">
                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs">
                        <i class="fa fa-user"></i>
                    </div>
                    <div class="ml-2 flex-1">
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-medium">${name}</span>
                            <span class="text-[10px] text-gray-400">${nowTime}</span>
                        </div>
                        <img src="${imgSrc}" class="mt-1 max-h-32 rounded-md cursor-pointer chat-img" data-src="${imgSrc}">
                    </div>
                </div>
            `;
            chatMessages.appendChild(el);
            // 绑定放大
            el.querySelector('.chat-img').onclick = function(){
                bigImg.src = this.dataset.src;
                imgMask.style.display = 'flex';
            };
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // 发送文字
        function sendMessage() {
            const msg = messageInput.value.trim();
            const user = usernameInput.value.trim() || '匿名听众';
            if(!msg) return;
            fetch('', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `action=send_message&username=${encodeURIComponent(user)}&message=${encodeURIComponent(msg)}&img_url=`
            }).then(res=>res.json()).then(()=>{
                messageInput.value='';
                loadMessages();
            });
        }
        
        // 加载历史消息
        function loadMessages() {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: 'action=get_messages'
            }).then(res=>res.json()).then(messages=>{
                chatMessages.innerHTML = '';
                if(messages.length===0){
                    chatMessages.innerHTML = '<div class="text-center text-gray-400 text-sm py-4">暂无消息</div>';
                    return;
                }
                messages.forEach(m=>{
                    const el = document.createElement('div');
                    el.className = 'mb-2 pb-2 border-b border-gray-100';
                    let html = `
                    <div class="flex items-start">
                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-primary text-xs">
                            <i class="fa fa-user"></i>
                        </div>
                        <div class="ml-2 flex-1">
                            <div class="flex items-center gap-1">
                                <span class="text-xs font-medium">${m.username}</span>
                                <span class="text-[10px] text-gray-400">${m.time}</span>
                            </div>
                    `;
                    if(m.message && m.message.trim()!=='') html += `<p class="text-sm mt-1">${m.message}</p>`;
                    if(m.img_url) html += `<img src="${m.img_url}" class="mt-1 max-h-32 rounded-md cursor-pointer chat-img" data-src="${m.img_url}">`;
                    html += `</div></div>`;
                    el.innerHTML = html;
                    chatMessages.appendChild(el);
                });
                document.querySelectorAll('.chat-img').forEach(img=>{
                    img.onclick = function(){ bigImg.src = this.dataset.src; imgMask.style.display = 'flex'; }
                });
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }
        
        function clearChat(){
            if(!confirm('确定清空所有聊天记录吗？'))return;
            fetch('',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=clear_chat'
            }).then(res=>res.json()).then(()=>loadMessages());
        }
        function updateOnlineCount(){
            fetch('',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_online_count'
            }).then(res=>res.json()).then(data=>{onlineCountEl.textContent=data.count;});
        }
        
        sendMessageBtn.addEventListener('click', sendMessage);
        clearChatBtn.addEventListener('click', clearChat);
        messageInput.addEventListener('keypress',e=>{if(e.key==='Enter')sendMessage();});
        window.addEventListener('load',()=>{
            loadMessages();
            updateVolumeDisplay();
            setInterval(loadMessages,8000);
            setInterval(updateOnlineCount,10000);
        });
    </script>
</body>
</html>
