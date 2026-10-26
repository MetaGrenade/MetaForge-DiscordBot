import 'dotenv/config';
import { Client, Events, GatewayIntentBits, REST, Routes, SlashCommandBuilder } from 'discord.js';
import { postSignedMetaForgeRequest } from './metaforgeApi.js';
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

type LinkStatusResponse = {
  linked: boolean;
  discord_user_id: string;
  user?: { id: number; name: string; email?: string | null };
};

type RolesResponse = {
  linked: boolean;
  discord_user_id: string;
  spatie_roles?: string[];
  mapped_discord_roles?: string[];
};

async function registerCommands(): Promise<void> {
  if (!applicationId || !guildId) {
    console.warn('Skipping slash command registration (DISCORD_APPLICATION_ID or DISCORD_GUILD_ID missing).');
    return;
  }

  const commands = [
    new SlashCommandBuilder().setName('ping').setDescription('Check whether the MetaForge Discord bot is online.'),
    new SlashCommandBuilder()
      .setName('link')
      .setDescription('Show whether your Discord account is linked to MetaForge.'),
    new SlashCommandBuilder()
      .setName('roles')
      .setDescription('Show your MetaForge roles and mapped Discord roles (read-only).'),
    new SlashCommandBuilder()
      .setName('ticket')
      .setDescription('Staff: fetch a short support ticket summary from MetaForge.')
      .addIntegerOption((option) =>
        option.setName('id').setDescription('MetaForge support ticket ID').setRequired(true),
      ),
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

client.on(Events.GuildMemberUpdate, async (previous, member) => {
  if (guildId && member.guild.id !== guildId) {
    return;
  }

  if (!metaforgeWebhookUrl || !metaforgeWebhookSecret) {
    return;
  }

  const previousRoles = previous.roles.cache.map((role) => role.id).sort();
  const nextRoles = member.roles.cache.map((role) => role.id).sort();

  if (previousRoles.join(',') === nextRoles.join(',')) {
    return;
  }

  try {
    await postMetaForgeEvent(metaforgeWebhookUrl, metaforgeWebhookSecret, 'guild.member.update', {
      discord_user_id: member.id,
      guild_id: member.guild.id,
      roles: nextRoles,
      previous_roles: previousRoles,
    });
  } catch (error) {
    console.error('Failed to forward guild.member.update:', error);
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
    return;
  }

  if (interaction.commandName === 'ticket') {
    if (!metaforgeWebhookUrl || !metaforgeWebhookSecret) {
      await interaction.reply({
        content: 'MetaForge API is not configured on this bot.',
        ephemeral: true,
      });
      return;
    }

    const ticketId = interaction.options.getInteger('id', true);
    const memberRoleIds =
      interaction.member && 'roles' in interaction.member && 'cache' in interaction.member.roles
        ? interaction.member.roles.cache.map((role) => role.id)
        : [];

    await interaction.deferReply({ ephemeral: true });

    try {
      const data = await postSignedMetaForgeRequest<{ ok: boolean; summary?: string; message?: string }>(
        metaforgeWebhookUrl,
        metaforgeWebhookSecret,
        '/discord/bot/support-tickets/summary',
        {
          discord_user_id: interaction.user.id,
          ticket_id: ticketId,
          member_role_ids: memberRoleIds,
        },
      );

      if (!data.ok) {
        await interaction.editReply(data.message ?? 'Could not load ticket summary.');
        return;
      }

      await interaction.editReply(data.summary ?? 'No summary returned.');
    } catch (error) {
      console.error('Failed /ticket command:', error);
      await interaction.editReply('Could not reach MetaForge or you are not authorized.');
    }

    return;
  }

  if (interaction.commandName === 'link' || interaction.commandName === 'roles') {
    if (!metaforgeWebhookUrl || !metaforgeWebhookSecret) {
      await interaction.reply({
        content: 'MetaForge API is not configured on this bot (METAFORGE_WEBHOOK_URL / METAFORGE_WEBHOOK_SECRET).',
        ephemeral: true,
      });
      return;
    }

    await interaction.deferReply({ ephemeral: true });

    try {
      if (interaction.commandName === 'link') {
        const data = await postSignedMetaForgeRequest<LinkStatusResponse>(
          metaforgeWebhookUrl,
          metaforgeWebhookSecret,
          '/discord/bot/members/link-status',
          { discord_user_id: interaction.user.id },
        );

        if (!data.linked) {
          await interaction.editReply(
            'Your Discord account is **not linked** to MetaForge. Sign in at the site with Discord OAuth to link.',
          );
          return;
        }

        const label = data.user?.name ?? `user #${data.user?.id ?? '?'}`;
        await interaction.editReply(`Your Discord account is linked to MetaForge as **${label}**.`);
        return;
      }

      const data = await postSignedMetaForgeRequest<RolesResponse>(
        metaforgeWebhookUrl,
        metaforgeWebhookSecret,
        '/discord/bot/members/roles',
        { discord_user_id: interaction.user.id },
      );

      if (!data.linked) {
        await interaction.editReply('Link your Discord account via MetaForge OAuth before checking roles.');
        return;
      }

      const spatie = data.spatie_roles?.length ? data.spatie_roles.join(', ') : '(none)';
      const mapped = data.mapped_discord_roles?.length ? data.mapped_discord_roles.join(', ') : '(none)';
      await interaction.editReply(
        `**MetaForge roles:** ${spatie}\n**Mapped Discord role IDs:** ${mapped}\n\nRole changes are applied by MetaForge; this command is read-only.`,
      );
    } catch (error) {
      console.error(`Failed ${interaction.commandName} command:`, error);
      await interaction.editReply('Could not reach MetaForge. Try again later or contact staff.');
    }
  }
});

await registerCommands();
await client.login(discordToken);
