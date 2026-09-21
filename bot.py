import discord
import os
import subprocess
import json
import threading
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from dotenv import load_dotenv
from groq import Groq

# Load environment variables
load_dotenv()
DISCORD_TOKEN = os.getenv('DISCORD_TOKEN')
GROQ_API_KEY = os.getenv('GROQ_API_KEY')

# Initialize Groq client
groq_client = Groq(api_key=GROQ_API_KEY)

# Initialize Discord Bot
intents = discord.Intents.default()
intents.message_content = True
client = discord.Client(intents=intents)

# System prompt for Groq
SYSTEM_PROMPT = """You are a fun, warm, and slightly witty AI assistant managing a MacBook Pro 2011 home server at 10.247.192.231.
You provide very simple, concise, and conversational answers. Strictly avoid walls of text.

System Architecture & Services:
- Music Streaming Portal: Aether, a high-performance modern React/Vite/Tailwind SPA.
    * Aether connects to the MAMP Ampache backend via the Subsonic REST API.
    * Users manage Recently Added music, Favorite Albums, and Favorite Artists directly in the Aether React interface, not on the main ServerFlow HTML dashboard.
    * Backend: Ampache v6 running on MAMP (Apache port 8888) on macOS High Sierra.
  * Music Storage: The audio catalog resides on the 'mac2' Windows node mounted over SMB to /Volumes/Music/.
  * Features & Navigation: Users can browse All Songs (with infinite scroll), Albums, Artists, their private Liked Songs playlist (pinned on Dashboard), and manage custom genres or snap stray tracks into place.
- Dashboard & Portals: Includes Server Deployer, Plex Portal, USB Storage Manager, and "Friend Hosting" (which uses relative-path rules).

Automated Service Repairs:
You can automatically fix server issues if the user asks in plain English or enters quick commands (!fixplex, !fixweb, !fixdb).
If the user asks to fix, restart, or repair a service, you MUST include the corresponding exact execution tag anywhere in your response:
- Plex / Media Server (or !fixplex): [ACTION: FIX_PLEX]
- Web Services / Aether / Server Deployer (or !fixweb): [ACTION: FIX_WEB]
- Database / MySQL / MAMP (or !fixdb): [ACTION: FIX_DB]

When using a tag, also include a short, friendly message letting the user know you're on it. Do not explain the tags to the user; they are for backend processing."""

ACTION_CONFIG = {
    "[ACTION: FIX_PLEX]": {
        "script": "./scripts/restart_plex.sh",
        "working_msg": "🎬 *Spinning up the film reels... restarting Plex!*",
        "success_msg": "✅ Plex is back online!",
        "error_msg": "❌ Uh oh, hit a snag with Plex:"
    },
    "[ACTION: FIX_WEB]": {
        "script": "./scripts/restart_web.sh",
        "working_msg": "🌐 *Tinkering with the web tubes... reloading Web Services!*",
        "success_msg": "✅ Web services are purring again!",
        "error_msg": "❌ Oops, web services are stubborn today:"
    },
    "[ACTION: FIX_DB]": {
        "script": "./scripts/restart_db.sh",
        "working_msg": "🗄️ *Dusting off the tables... restarting the Database!*",
        "success_msg": "✅ Database is healthy and fresh!",
        "error_msg": "❌ DB is being grumpy:"
    }
}

def process_ai_chat(clean_content, history=None):
    """
    Central AI processor for both HTTP requests (Aether React ChatWidget)
    and Discord Bot direct messages/mentions.
    """
    if not clean_content:
        return {"response": "Hi! How can I help you today?", "action_result": None}

    messages = [{"role": "system", "content": SYSTEM_PROMPT}]
    if history and isinstance(history, list):
        for h in history[-8:]:  # keep last 8 messages for context window
            if isinstance(h, dict) and "role" in h and "content" in h:
                messages.append({"role": h["role"], "content": h["content"]})
    messages.append({"role": "user", "content": clean_content})

    try:
        chat_completion = groq_client.chat.completions.create(
            messages=messages,
            model="llama3-8b-8192",
            temperature=0.7,
            max_tokens=800
        )
        response = chat_completion.choices[0].message.content or ""
    except Exception as e:
        return {"response": f"AI service error: {str(e)}", "action_result": None}

    # Detect and execute action tags
    action_result = None
    for tag, config in ACTION_CONFIG.items():
        if tag in response:
            clean_resp = response.replace(tag, "").strip()
            action_script = config["script"]
            script_path = os.path.join(os.path.dirname(__file__), action_script) if not os.path.isabs(action_script) else action_script
            
            try:
                if os.path.exists(script_path):
                    res = subprocess.run([script_path], capture_output=True, text=True, check=True)
                    out = res.stdout.strip()
                    msg = f"{config['success_msg']}" + (f"\n{out}" if out else "")
                else:
                    msg = f"{config['working_msg']} (Script simulated on host)"
                action_result = msg
                response = f"{clean_resp}\n\n{msg}" if clean_resp else msg
            except subprocess.CalledProcessError as e:
                err_out = e.stderr.strip() if e.stderr else str(e)
                action_result = f"{config['error_msg']} {err_out}"
                response = f"{clean_resp}\n\n{action_result}" if clean_resp else action_result
            except Exception as e:
                action_result = f"{config['error_msg']} {str(e)}"
                response = f"{clean_resp}\n\n{action_result}" if clean_resp else action_result
            break

    return {"response": response.strip(), "action_result": action_result}


# ─── Dual HTTP Server for Port 5005 (Aether React ChatWidget & PHP Gateway) ───
class ChatRequestHandler(BaseHTTPRequestHandler):
    def _send_cors_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')

    def do_OPTIONS(self):
        self.send_response(200)
        self._send_cors_headers()
        self.end_headers()

    def do_POST(self):
        normalized_path = self.path.split('?')[0].rstrip('/')
        if normalized_path in ['/api/chat', '/chat']:
            try:
                content_length = int(self.headers.get('Content-Length', 0))
                body = self.rfile.read(content_length).decode('utf-8') if content_length > 0 else '{}'
                data = json.loads(body) if body else {}

                user_msg = data.get('message') or data.get('prompt') or ''
                history = data.get('history') or []

                result = process_ai_chat(user_msg, history)

                self.send_response(200)
                self.send_header('Content-Type', 'application/json; charset=utf-8')
                self._send_cors_headers()
                self.end_headers()

                payload = {
                    "status": "success",
                    "reply": result["response"],
                    "response": result["response"],
                    "answer": result["response"]
                }
                self.wfile.write(json.dumps(payload).encode('utf-8'))
            except Exception as err:
                self.send_response(500)
                self.send_header('Content-Type', 'application/json')
                self._send_cors_headers()
                self.end_headers()
                self.wfile.write(json.dumps({"status": "error", "message": str(err)}).encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()

    def do_GET(self):
        normalized_path = self.path.split('?')[0].rstrip('/')
        if normalized_path in ['/health', '/api/health', '/status', '']:
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self._send_cors_headers()
            self.end_headers()
            self.wfile.write(json.dumps({
                "status": "ok",
                "service": "Aether AI Assistant",
                "model": "llama3-8b-8192",
                "server": "MacBook Pro 2011"
            }).encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()

    def log_message(self, format, *args):
        # Prevent noisy terminal outputs
        pass


def start_http_server(port=5005):
    try:
        server = ThreadingHTTPServer(('0.0.0.0', port), ChatRequestHandler)
        print(f"🚀 [Aether AI] HTTP API Server running on port {port} (Listening on /api/chat and /chat)")
        server.serve_forever()
    except Exception as e:
        print(f"⚠️ [Aether AI] HTTP Server could not bind port {port}: {e}")


# ─── Discord Event Handlers ───
@client.event
async def on_ready():
    print(f'Logged in as Discord Bot: {client.user}')

@client.event
async def on_message(message):
    if message.author == client.user:
        return

    # Check if bot is mentioned or if it's a DM
    if client.user in message.mentions or isinstance(message.channel, discord.DMChannel):
        clean_content = message.content.replace(f'<@{client.user.id}>', '').strip()
        
        async with message.channel.typing():
            authorized_admin_ids = [x.strip() for x in os.getenv('DISCORD_ADMIN_USER_IDS', '').split(',') if x.strip()]
            
            # If user asks to run an action but isn't authorized
            result = process_ai_chat(clean_content)
            await message.channel.send(result["response"])


# ─── Process Launch ───
if __name__ == '__main__':
    # 1. Start HTTP Server in background daemon thread
    http_thread = threading.Thread(target=start_http_server, args=(5005,), daemon=True)
    http_thread.start()

    # 2. Run Discord client if token configured, otherwise keep daemon thread alive
    if DISCORD_TOKEN and DISCORD_TOKEN != "YOUR_DISCORD_TOKEN_HERE":
        try:
            client.run(DISCORD_TOKEN)
        except Exception as e:
            print(f"[Discord] Client stopped ({e}). HTTP Server remains active on port 5005.")
            import time
            while True:
                time.sleep(3600)
    else:
        print("[Discord] Valid token not found. Running Aether AI in HTTP-only mode on port 5005.")
        import time
        while True:
            time.sleep(3600)
