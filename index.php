<?php
// 配置
$chatFile = 'chat_messages.txt';
$usersFile = 'online_users.txt';
$cleanupTime = 300; // 5分钟无活动则视为离线

// 处理在线用户
function updateOnlineUsers() {
    global $usersFile, $cleanupTime;
    
    $userId = session_id();
    if(empty($userId)) {
        session_start();
        $userId = session_id();
    }
    
    $currentTime = time();
    $users = array();
    
    // 读取现有用户
    if(file_exists($usersFile)) {
        $data = file_get_contents($usersFile);
        $users = unserialize($data);
    }
    
    // 更新当前用户时间戳
    $users[$userId] = $currentTime;
    
    // 清理过期用户
    foreach($users as $id => $time) {
        if($currentTime - $time > $cleanupTime) {
            unset($users[$id]);
        }
    }
    
    // 保存更新后的用户列表
    file_put_contents($usersFile, serialize($users));
    
    return count($users);
}

// 获取在线用户数
function getOnlineUsersCount() {
    global $usersFile, $cleanupTime;
    
    if(!file_exists($usersFile)) {
        return 0;
    }
    
    $currentTime = time();
    $data = file_get_contents($usersFile);
    $users = unserialize($data);
    
    // 清理过期用户
    foreach($users as $id => $time) {
        if($currentTime - $time > $cleanupTime) {
            unset($users[$id]);
        }
    }
    
    return count($users);
}

// 处理聊天消息
function handleChatMessage() {
    global $chatFile;
    
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty($_POST['message'])) {
        $message = trim($_POST['message']);
        $username = isset($_POST['username']) ? trim($_POST['username']) : '匿名听众';
        $username = htmlspecialchars($username);
        $message = htmlspecialchars($message);
        
        if(strlen($message) > 0 && strlen($message) <= 500) {
            $time = date('H:i');
            $entry = array(
                'time' => $time,
                'username' => $username,
                'message' => $message
            );
            
            $messages = array();
            if(file_exists($chatFile)) {
                $data = file_get_contents($chatFile);
                $messages = unserialize($data);
            }
            
            // 限制消息数量为100条
            if(count($messages) >= 100) {
                array_shift($messages);
            }
            
            $messages[] = $entry;
            file_put_contents($chatFile, serialize($messages));
            
            return true;
        }
    }
    
    return false;
}

// 获取聊天消息
function getChatMessages() {
    global $chatFile;
    
    if(!file_exists($chatFile)) {
        return array();
    }
    
    $data = file_get_contents($chatFile);
    return unserialize($data);
}

// 处理请求
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['action']) && $_POST['action'] === 'send_message') {
        handleChatMessage();
        header('Content-Type: application/json');
        echo json_encode(array('status' => 'success'));
        exit;
    } elseif(isset($_POST['action']) && $_POST['action'] === 'get_messages') {
        $messages = getChatMessages();
        header('Content-Type: application/json');
        echo json_encode($messages);
        exit;
    } elseif(isset($_POST['action']) && $_POST['action'] === 'get_online_count') {
        $count = getOnlineUsersCount();
        header('Content-Type: application/json');
        echo json_encode(array('count' => $count));
        exit;
    }
}

// 更新在线用户
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
                    colors: {
                        primary: '#3B82F6',
                        secondary: '#10B981',
                        accent: '#F59E0B',
                        dark: '#1E293B',
                        light: '#F8FAFC'
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .text-shadow {
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .backdrop-blur {
                backdrop-filter: blur(8px);
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .player-gradient {
                background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%);
            }
            .volume-slider::-webkit-slider-thumb {
                -webkit-appearance: none;
                appearance: none;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #3B82F6;
                cursor: pointer;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans text-gray-800">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- 头部 -->
        <header class="text-center mb-8">
            <h1 class="text-[clamp(2rem,5vw,3.5rem)] font-bold text-primary text-shadow mb-2">
                <i class="fa fa-music mr-3"></i>清晨音乐台
            </h1>
            <p class="text-gray-600 text-lg">用音乐唤醒美好的一天</p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- 播放器区域 -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition-all duration-300 hover:shadow-xl">
                    <!-- 播放器封面 -->
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

                    <!-- 音频播放器 -->
                    <div class="p-6">
                        <div class="mb-4">
                            <h2 class="text-xl font-semibold mb-1">清晨音乐台直播</h2>
                            <p class="text-gray-500 text-sm">美好的一天从音乐开始</p>
                        </div>
                        
                        <audio id="radio-player" class="w-full" controls>
                            <source src="https://lhttp.qingting.fm/live/4915/64k.mp3" type="audio/mpeg">
                            您的浏览器不支持音频播放
                        </audio>
                        
                        <!-- 音量控制区域 -->
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

                <!-- 介绍区域 -->
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

            <!-- 聊天区域 -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg h-full flex flex-col">
                    <div class="p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold flex items-center">
                            <i class="fa fa-comments text-accent mr-2"></i> 听众聊天室
                        </h3>
                    </div>
                    
                    <div id="chat-messages" class="flex-1 p-4 overflow-y-auto scrollbar-hide">
                        <!-- 聊天消息将通过JS动态加载 -->
                        <div class="text-center text-gray-500 text-sm py-4">
                            欢迎加入聊天室，与其他听众交流
                        </div>
                    </div>
                    
                    <div class="p-4 border-t border-gray-200">
                        <div class="mb-3">
                            <input type="text" id="username" placeholder="请输入您的昵称" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all text-sm">
                        </div>
                        <div class="flex">
                            <input type="text" id="message-input" placeholder="输入消息..." 
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all text-sm">
                            <button id="send-message" class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-r-lg transition-all">
                                <i class="fa fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 页脚 -->
        <footer class="mt-12 text-center text-gray-500 text-sm">
            <p>© 2023 清晨音乐台 - 在线播放器</p>
            <p class="mt-1">本播放器仅供学习交流使用</p>
        </footer>
    </div>

    <script>
        // 播放器控制
        const audio = document.getElementById('radio-player');
        const volumeUpBtn = document.getElementById('volume-up');
        const volumeDownBtn = document.getElementById('volume-down');
        const volumeMuteBtn = document.getElementById('volume-mute');
        const volumeSlider = document.getElementById('volume-slider');
        const volumePercentage = document.getElementById('volume-percentage');
        const fullscreenBtn = document.getElementById('fullscreen');
        
        // 设置默认音量为80%
        audio.volume = 0.8;
        let lastVolume = 0.8; // 用于静音后恢复音量
        
        // 更新音量显示
        function updateVolumeDisplay() {
            const percentage = Math.round(audio.volume * 100);
            volumePercentage.textContent = `${percentage}%`;
            volumeSlider.value = percentage;
            
            // 更新音量图标
            if (audio.muted || percentage === 0) {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-off text-gray-700"></i><span class="ml-2">静音</span>';
            } else if (percentage < 50) {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-down text-gray-700"></i><span class="ml-2">恢复音量</span>';
            } else {
                volumeMuteBtn.innerHTML = '<i class="fa fa-volume-up text-gray-700"></i><span class="ml-2">静音</span>';
            }
        }
        
        // 音量增加5%
        volumeUpBtn.addEventListener('click', () => {
            if (audio.muted) {
                audio.muted = false;
                audio.volume = lastVolume;
            } else {
                audio.volume = Math.min(1, audio.volume + 0.05);
            }
            updateVolumeDisplay();
            showNotification(`音量已调整至 ${Math.round(audio.volume * 100)}%`);
        });
        
        // 音量减少5%
        volumeDownBtn.addEventListener('click', () => {
            if (audio.muted) {
                audio.muted = false;
                audio.volume = lastVolume;
            } else {
                audio.volume = Math.max(0, audio.volume - 0.05);
            }
            updateVolumeDisplay();
            showNotification(`音量已调整至 ${Math.round(audio.volume * 100)}%`);
        });
        
        // 静音切换
        volumeMuteBtn.addEventListener('click', () => {
            if (audio.muted) {
                // 从静音恢复
                audio.muted = false;
                audio.volume = lastVolume;
                showNotification(`音量已恢复至 ${Math.round(audio.volume * 100)}%`);
            } else {
                // 静音
                lastVolume = audio.volume;
                audio.muted = true;
                showNotification('已静音');
            }
            updateVolumeDisplay();
        });
        
        // 滑块控制音量
        volumeSlider.addEventListener('input', () => {
            const volume = volumeSlider.value / 100;
            audio.volume = volume;
            audio.muted = false;
            lastVolume = volume;
            updateVolumeDisplay();
        });
        
        // 全屏功能
        fullscreenBtn.addEventListener('click', () => {
            const playerContainer = audio.closest('.bg-white');
            if (!document.fullscreenElement) {
                playerContainer.requestFullscreen().catch(err => {
                    showNotification(`全屏请求失败: ${err.message}`);
                });
                showNotification('已进入全屏模式');
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                    showNotification('已退出全屏模式');
                }
            }
        });
        
        // 聊天功能
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const usernameInput = document.getElementById('username');
        const sendMessageBtn = document.getElementById('send-message');
        const onlineCountEl = document.getElementById('online-count');
        
        // 发送消息
        function sendMessage() {
            const message = messageInput.value.trim();
            const username = usernameInput.value.trim() || '匿名听众';
            
            if (message) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_message&username=${encodeURIComponent(username)}&message=${encodeURIComponent(message)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        messageInput.value = '';
                        loadMessages();
                    }
                });
            }
        }
        
        // 加载消息
        function loadMessages() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_messages'
            })
            .then(response => response.json())
            .then(messages => {
                chatMessages.innerHTML = '';
                
                if (messages.length === 0) {
                    chatMessages.innerHTML = `
                        <div class="text-center text-gray-500 text-sm py-4">
                            还没有消息，发送第一条消息吧！
                        </div>
                    `;
                    return;
                }
                
                messages.forEach(msg => {
                    const messageEl = document.createElement('div');
                    messageEl.className = 'mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0';
                    
                    messageEl.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-8 w-8 rounded-full bg-primary/20 flex items-center justify-center text-primary">
                                <i class="fa fa-user"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-900">${msg.username}</span>
                                    <span class="ml-2 text-xs text-gray-500">${msg.time}</span>
                                </div>
                                <p class="mt-1 text-sm text-gray-700">${msg.message}</p>
                            </div>
                        </div>
                    `;
                    
                    chatMessages.appendChild(messageEl);
                });
                
                // 滚动到底部
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }
        
        // 更新在线人数
        function updateOnlineCount() {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_online_count'
            })
            .then(response => response.json())
            .then(data => {
                onlineCountEl.textContent = data.count;
            });
        }
        
        // 通知功能
        function showNotification(text) {
            // 检查是否已有通知
            let notification = document.querySelector('.custom-notification');
            if (notification) {
                notification.remove();
            }
            
            // 创建新通知
            notification = document.createElement('div');
            notification.className = 'custom-notification fixed bottom-4 right-4 bg-dark text-white px-4 py-2 rounded-lg shadow-lg transform transition-all duration-300 translate-y-0 opacity-100';
            notification.textContent = text;
            document.body.appendChild(notification);
            
            // 3秒后隐藏
            setTimeout(() => {
                notification.classList.add('opacity-0', 'translate-y-4');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
        
        // 事件监听
        sendMessageBtn.addEventListener('click', sendMessage);
        
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // 页面加载完成后加载消息和初始化音量显示
        window.addEventListener('load', () => {
            loadMessages();
            updateVolumeDisplay();
            
            // 自动播放提示
            if (audio.paused) {
                showNotification('点击播放按钮开始收听清晨音乐台');
            }
            
            // 定时刷新消息和在线人数
            setInterval(loadMessages, 5000);
            setInterval(updateOnlineCount, 10000);
        });
        
        // 音频播放状态变化
        audio.addEventListener('play', () => {
            showNotification('正在播放清晨音乐台');
        });
        
        audio.addEventListener('pause', () => {
            showNotification('已暂停播放');
        });
        
        audio.addEventListener('error', () => {
            showNotification('播放出错，请尝试刷新页面');
        });
    </script>
</body>
</html>
    
