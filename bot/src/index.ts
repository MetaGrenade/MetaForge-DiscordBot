import 'dotenv/config';
import { Client, Events, GatewayIntentBits, REST, Routes, SlashCommandBuilder } from 'discord.js';
import { postMetaForgeEvent } from './metaforgeWebhook.js';

const botToken = process.env.DISCORD_BOT_TOKEN;
const guildId = process.env.DISCORD_GUILD_ID;
const applicationId = process.env.DISCORD_APPLICATION_ID;
const metaforgeWebhookUrl = process.env.METAFORGE_WEBHOOK_URL;
const metaforgeWebhookSecret = process.env.METAFORGE_WEBHOOK_SECRET;

if (!botToken) {
  throw new Error('DISCORD_BOT_TOKEN is required');
}

/** Narrowed bot token (required at startup). */
const discordToken: string = botToken;

const client = new Client({
  intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildMembers],
});

async function registerCommands(): Promise<void> {
  if (!applicationId || !guildId) {
    console.warn('Skipping slash command registration (DISCORD_APPLICATION_ID or DISCORD_GUILD_ID missing).');
    return;
  }

  const commands = [
    new SlashCommandBuilder().setName('ping').setDescription('Check whether the MetaForge Discord bot is online.'),
  ].map((command) => command.toJSON());

  const rest = new REST({ version: '10' }).setToken(discordToken);
  await rest.put(Routes.applicationGuildCommands(applicationId, guildId), { body: commands });
}

client.once(Events.ClientReady, async (readyClient) => {
  console.log(`Logged in as ${readyClient.user.tag}`);

  if (metaforgeWebhookUrl && metaforgeWebhookSecret) {
    try {
      await postMetaForgeEvent(metaforgeWebhookUrl, metaforgeWebhookSecret, 'bot.ready', {
        guild_id: guildId ?? null,
        bot_user: readyClient.user.id,
      });
    } catch (error) {
      console.error('Failed to notify MetaForge:', error);
    }
  }
});

client.on(Events.GuildMemberAdd, async (member) => {
  if (guildId && member.guild.id !== guildId) {
    return;
  }

  if (!metaforgeWebhookUrl || !metaforgeWebhookSecret) {
    return;
  }

  try {
    await postMetaForgeEvent(metaforgeWebhookUrl, metaforgeWebhookSecret, 'guild.member.add', {
      discord_user_id: member.id,
      guild_id: member.guild.id,
    });
  } catch (error) {
    console.error('Failed to forward guild.member.add:', error);
  }
});

client.on(Events.InteractionCreate, async (interaction) => {
  if (!interaction.isChatInputCommand()) {
    return;
  }

  if (interaction.commandName === 'ping') {
    await interaction.reply({ content: 'MetaForge Discord bot is online.', ephemeral: true });
  }
});

await registerCommands();
await client.login(discordToken);
