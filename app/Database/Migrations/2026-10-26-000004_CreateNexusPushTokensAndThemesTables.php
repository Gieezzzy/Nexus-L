<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000004:
 * Adds push token storage, dynamic themes catalog, group members tracking,
 * notification logging, and relational reply columns on tbl_Messages in dbnexus.
 */
class CreateNexusPushTokensAndThemesTables extends Migration
{
    public function up()
    {
        // 1. tbl_user_push_tokens (FCM Web Push tokens for computer notifications)
        if (! $this->db->tableExists('tbl_user_push_tokens')) {
            $this->forge->addField([
                'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'     => ['type' => 'VARCHAR', 'constraint' => 100],
                'token'       => ['type' => 'VARCHAR', 'constraint' => 500],
                'device_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'web'],
                'browser'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'endpoint'    => ['type' => 'TEXT', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey('user_id');
            $this->forge->addUniqueKey('token');
            $this->forge->createTable('tbl_user_push_tokens', true, ['ENGINE' => 'InnoDB']);
        }

        // 2. tbl_themes (Theme Studio & dynamic animation catalog: Sadako, Christmas, Valentines, Anime, etc.)
        if (! $this->db->tableExists('tbl_themes')) {
            $this->forge->addField([
                'theme_id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'theme_key'             => ['type' => 'VARCHAR', 'constraint' => 50],
                'theme_name'            => ['type' => 'VARCHAR', 'constraint' => 100],
                'background_url'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'sender_bubble_color'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'receiver_bubble_color' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'primary_color'         => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'secondary_color'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'text_color'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'animation_type'        => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'none'],
                'trigger_emoji'         => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'sound_effect_url'      => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'is_active'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at'            => ['type' => 'DATETIME', 'null' => true],
                'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('theme_id');
            $this->forge->addUniqueKey('theme_key');
            $this->forge->createTable('tbl_themes', true, ['ENGINE' => 'InnoDB']);

            // Seed initial themes
            $now = date('Y-m-d H:i:s');
            $this->db->table('tbl_themes')->insertBatch([
                [
                    'theme_key'             => 'default',
                    'theme_name'            => 'Default RMS Blue',
                    'background_url'        => null,
                    'sender_bubble_color'   => '#2563EB',
                    'receiver_bubble_color' => '#E2E8F0',
                    'primary_color'         => '#2563EB',
                    'secondary_color'       => '#3B82F6',
                    'text_color'            => '#0F172A',
                    'animation_type'        => 'none',
                    'trigger_emoji'         => '👍',
                    'sound_effect_url'      => null,
                    'is_active'             => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                [
                    'theme_key'             => 'christmas',
                    'theme_name'            => 'Christmas Holiday Snow',
                    'background_url'        => 'https://images.unsplash.com/photo-1543258103-a62bdc069871?auto=format&fit=crop&w=1200&q=80',
                    'sender_bubble_color'   => '#DC2626',
                    'receiver_bubble_color' => '#166534',
                    'primary_color'         => '#DC2626',
                    'secondary_color'       => '#16A34A',
                    'text_color'            => '#FFFFFF',
                    'animation_type'        => 'snow',
                    'trigger_emoji'         => '❄️',
                    'sound_effect_url'      => 'https://actions.google.com/sounds/v1/holidays/sleigh_bells.ogg',
                    'is_active'             => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                [
                    'theme_key'             => 'sadako',
                    'theme_name'            => 'Sadako Cursed Well Horror',
                    'background_url'        => 'https://images.unsplash.com/photo-1509248961158-e54f6934749c?auto=format&fit=crop&w=1200&q=80',
                    'sender_bubble_color'   => '#0F172A',
                    'receiver_bubble_color' => '#1E293B',
                    'primary_color'         => '#7F1D1D',
                    'secondary_color'       => '#991B1B',
                    'text_color'            => '#F8FAFC',
                    'animation_type'        => 'sadako',
                    'trigger_emoji'         => '👻',
                    'sound_effect_url'      => 'https://actions.google.com/sounds/v1/horror/ghost_groan.ogg',
                    'is_active'             => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                [
                    'theme_key'             => 'valentines',
                    'theme_name'            => 'Valentine Love Romance',
                    'background_url'        => 'https://images.unsplash.com/photo-1518199266791-5375a83190b7?auto=format&fit=crop&w=1200&q=80',
                    'sender_bubble_color'   => '#EC4899',
                    'receiver_bubble_color' => '#FCE7F3',
                    'primary_color'         => '#E11D48',
                    'secondary_color'       => '#F43F5E',
                    'text_color'            => '#881337',
                    'animation_type'        => 'hearts',
                    'trigger_emoji'         => '❤️',
                    'sound_effect_url'      => null,
                    'is_active'             => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                [
                    'theme_key'             => 'anime_cyberpunk',
                    'theme_name'            => 'Anime Cyberpunk Neon',
                    'background_url'        => 'https://images.unsplash.com/photo-1508739773434-c26b3d09e071?auto=format&fit=crop&w=1200&q=80',
                    'sender_bubble_color'   => '#8B5CF6',
                    'receiver_bubble_color' => '#06B6D4',
                    'primary_color'         => '#A855F7',
                    'secondary_color'       => '#3B82F6',
                    'text_color'            => '#F1F5F9',
                    'animation_type'        => 'sparkles',
                    'trigger_emoji'         => '⚡',
                    'sound_effect_url'      => null,
                    'is_active'             => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
            ]);
        }

        // 3. tbl_group_members (Normalized relational mapping for group chat membership)
        if (! $this->db->tableExists('tbl_group_members')) {
            $this->forge->addField([
                'id'                   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'group_id'             => ['type' => 'VARCHAR', 'constraint' => 100],
                'user_id'              => ['type' => 'VARCHAR', 'constraint' => 100],
                'role'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'member'],
                'joined_at'            => ['type' => 'DATETIME', 'null' => true],
                'last_read_message_id' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
                'is_muted'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['group_id', 'user_id']);
            $this->forge->createTable('tbl_group_members', true, ['ENGINE' => 'InnoDB']);
        }

        // 4. tbl_notification_logs (Notification audit log)
        if (! $this->db->tableExists('tbl_notification_logs')) {
            $this->forge->addField([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'user_id'    => ['type' => 'VARCHAR', 'constraint' => 100],
                'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'body'       => ['type' => 'TEXT', 'null' => true],
                'source'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'nexus'],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'sent'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addKey(['user_id', 'created_at']);
            $this->forge->createTable('tbl_notification_logs', true, ['ENGINE' => 'InnoDB']);
        }

        // 5. Add relational reply columns to tbl_Messages in dbnexus for full Android parity
        $tbl = $this->db->tableExists('tbl_Messages') ? 'tbl_Messages' : ($this->db->tableExists('tbl_messages') ? 'tbl_messages' : null);
        if ($tbl !== null) {
            $fieldsToAdd = [];
            if (! $this->db->fieldExists('ReplyToMessageID', $tbl)) {
                $fieldsToAdd['ReplyToMessageID'] = ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'ConnectedID'];
            }
            if (! $this->db->fieldExists('ReplyToSenderName', $tbl)) {
                $fieldsToAdd['ReplyToSenderName'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'ReplyToMessageID'];
            }
            if (! $this->db->fieldExists('ReplySnippet', $tbl)) {
                $fieldsToAdd['ReplySnippet'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'ReplyToSenderName'];
            }
            if (! empty($fieldsToAdd)) {
                $this->forge->addColumn($tbl, $fieldsToAdd);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('tbl_notification_logs', true);
        $this->forge->dropTable('tbl_group_members', true);
        $this->forge->dropTable('tbl_themes', true);
        $this->forge->dropTable('tbl_user_push_tokens', true);
        if ($this->db->tableExists('tbl_Messages')) {
            if ($this->db->fieldExists('ReplySnippet', 'tbl_Messages')) {
                $this->forge->dropColumn('tbl_Messages', 'ReplySnippet');
            }
            if ($this->db->fieldExists('ReplyToSenderName', 'tbl_Messages')) {
                $this->forge->dropColumn('tbl_Messages', 'ReplyToSenderName');
            }
            if ($this->db->fieldExists('ReplyToMessageID', 'tbl_Messages')) {
                $this->forge->dropColumn('tbl_Messages', 'ReplyToMessageID');
            }
        }
    }
}
