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
SYSTEM_PROMPT = """You are 'ServerHelperBot', an assistant for a MacBook Pro home server hosted at local IP 10.247.192.231. The server runs Plex (tunneled securely through ZeroTier), a main web front-end hosting websites, and custom databases for friends. If users ask general questions or need guidance, explain how to access these services concisely and warmly. If a user is completely stuck, let them know they can use the commands '!fixplex', '!fixweb', or '!fixdb' to trigger automated repairs."""

@client.event
async def on_ready():
    print(f'Logged in as {client.user}')

@client.event
async def on_message(message):
    if message.author == client.user:
        return

    # Check for direct commands
    if message.content == '!fixplex':
        await message.channel.send("🔄 Initiating Plex media server restart...")
        try:
            result = subprocess.run(['./scripts/restart_plex.sh'], capture_output=True, text=True, check=True)
            await message.channel.send(f"✅ Success: {result.stdout.strip()}")
        except Exception as e:
            await message.channel.send(f"❌ Error running script: {e}")
        return

    if message.content == '!fixweb':
        await message.channel.send("🔄 Initiating Web Front-end services reload...")
        try:
            result = subprocess.run(['./scripts/restart_web.sh'], capture_output=True, text=True, check=True)
            await message.channel.send(f"✅ Success: {result.stdout.strip()}")
        except Exception as e:
            await message.channel.send(f"❌ Error running script: {e}")
        return

    if message.content == '!fixdb':
        await message.channel.send("🔄 Initiating Database health check and restart...")
        try:
            result = subprocess.run(['./scripts/restart_db.sh'], capture_output=True, text=True, check=True)
            await message.channel.send(f"✅ Success: {result.stdout.strip()}")
        except Exception as e:
            await message.channel.send(f"❌ Error running script: {e}")
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
                await message.channel.send(response)
            except Exception as e:
                await message.channel.send(f"Sorry, I encountered an error communicating with the AI: {e}")

client.run(DISCORD_TOKEN)
