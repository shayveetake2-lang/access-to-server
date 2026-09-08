import re

with open('index.html', 'r', encoding='utf-8') as f:
    content = f.read()

with open('old_script2.js', 'r', encoding='utf-8') as f:
    old_script = f.read()

# The current content has a broken script block. We will replace it with old_script, then append the chat widget logic.

chat_widget_logic = """
        // File input label update
        document.getElementById('usb_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Choose a file...';
            document.getElementById('file-chosen').textContent = fileName;
        });

        // Floating Chat Widget Toggle
        function toggleChatWidget() {
            const chatWidget = document.getElementById('chat-widget-container');
            if (chatWidget.classList.contains('hidden')) {
                chatWidget.classList.remove('hidden');
                chatWidget.classList.add('flex');
            } else {
                chatWidget.classList.add('hidden');
                chatWidget.classList.remove('flex');
            }
        }
        
        // Placeholder for chat API submission
        function submitChatMessage(e) {
            e.preventDefault();
            const input = document.getElementById('chat-input-field');
            const msg = input.value.trim();
            if (!msg) return;
            
            const messagesContainer = document.getElementById('chat-messages');
            
            // Add user message
            messagesContainer.innerHTML += `
                <div class="flex justify-end mb-2">
                    <div class="bg-cyan-600 text-white text-xs px-3 py-2 rounded-xl rounded-br-none max-w-[85%] shadow-sm">
                        ${msg}
                    </div>
                </div>
            `;
            
            input.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Add placeholder for bot response
            setTimeout(() => {
                messagesContainer.innerHTML += `
                    <div class="flex justify-start mb-2">
                        <div class="bg-slate-700 text-slate-200 text-xs px-3 py-2 rounded-xl rounded-bl-none max-w-[85%] shadow-sm">
                            <span class="animate-pulse">Thinking... (API Not Connected)</span>
                        </div>
                    </div>
                `;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 500);
        }
    </script>
"""

# replace </script> in old_script with chat_widget_logic
new_script = old_script.replace('    </script>', chat_widget_logic)

# Replace the entire script block in current index.html
content = re.sub(r'    <!-- ================= JAVASCRIPT LOGIC ================= -->\n    <script>.*?</script>', '    <!-- ================= JAVASCRIPT LOGIC ================= -->\n' + new_script, content, flags=re.DOTALL)

with open('index.html', 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed index.html script block")
