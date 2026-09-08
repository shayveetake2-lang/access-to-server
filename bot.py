import discord
import os
import subprocess
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
SYSTEM_PROMPT = """You are a fun, warm, and slightly witty AI assistant managing a MacBook Pro 2011 home server.
You provide very simple, concise, and conversational answers. Strictly avoid walls of text.
You help users navigate the custom Tailwind CSS dashboard, which includes the Server Deployer, Plex Portal, USB Storage Manager, and "Friend Hosting" (which uses relative-path rules).

You have the ability to automatically fix server issues if the user asks for help in plain English.
If the user asks to fix, restart, or repair a service, you MUST include the corresponding exact execution tag anywhere in your response:
- Plex / Media Server: [ACTION: FIX_PLEX]
- Web / Dashboard / Server Deployer: [ACTION: FIX_WEB]
- Database: [ACTION: FIX_DB]

When using a tag, also include a short, friendly message letting the user know you're on it. Do not explain the tags to the user, they are for backend processing."""

@client.event
async def on_ready():
    print(f'Logged in as {client.user}')

@client.event
async def on_message(message):
    if message.author == client.user:
        return

    # Check if bot is mentioned or if it's a DM
    if client.user in message.mentions or isinstance(message.channel, discord.DMChannel):
        # Remove bot mention from message
        clean_content = message.content.replace(f'<@{client.user.id}>', '').strip()
        
        async with message.channel.typing():
            try:
                chat_completion = groq_client.chat.completions.create(
                    messages=[
                        {
                            "role": "system",
                            "content": SYSTEM_PROMPT,
                        },
                        {
                            "role": "user",
                            "content": clean_content,
                        }
                    ],
                    model="llama3-8b-8192",
                )
                response = chat_completion.choices[0].message.content
                
                # Action definitions
                action_tags = {
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
                
                executed_action = False
                for tag, config in action_tags.items():
                    if tag in response:
                        executed_action = True
                        clean_response = response.replace(tag, "").strip()
                        
                        # Send AI's conversational reply if any
                        if clean_response:
                            await message.channel.send(clean_response)
                            
                        # Send the working status
                        await message.channel.send(config["working_msg"])
                        
                        try:
                            result = subprocess.run([config["script"]], capture_output=True, text=True, check=True)
                            out = result.stdout.strip()
                            await message.channel.send(f"{config['success_msg']}" + (f"\n```text\n{out}\n```" if out else ""))
                        except subprocess.CalledProcessError as e:
                            err_out = e.stderr.strip() if e.stderr else (e.stdout.strip() if e.stdout else str(e))
                            await message.channel.send(f"{config['error_msg']}\n```text\n{err_out}\n```" if err_out else f"{config['error_msg']} Exit code {e.returncode}")
                        except Exception as e:
                            await message.channel.send(f"{config['error_msg']} {e}")
                        
                        break # Process one action at a time

                if not executed_action and response.strip():
                    await message.channel.send(response.strip())
                    
            except Exception as e:
                await message.channel.send(f"Sorry, I encountered an error communicating with the AI: {e}")

client.run(DISCORD_TOKEN)
