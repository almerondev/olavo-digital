<?php

namespace App\Modules\Access\Services;

/**
 * Lista oficial das permissões. Papéis combinam estes valores no painel,
 * mas uma permissão nova sempre nasce aqui, junto do código que a verifica.
 */
final class PermissionCatalog
{
    /** @return array<string, array{module: string, description: string}> */
    public static function all(): array
    {
        return [
            'panel.admin' => ['module' => 'paineis', 'description' => 'Acessar o painel administrativo'],
            'panel.teacher' => ['module' => 'paineis', 'description' => 'Acessar o painel do professor'],
            'content.view' => ['module' => 'conteudo', 'description' => 'Ver conteúdos liberados, favoritos e histórico'],
            'materials.create' => ['module' => 'materiais', 'description' => 'Criar materiais'],
            'materials.update' => ['module' => 'materiais', 'description' => 'Editar materiais'],
            'materials.manage_all' => ['module' => 'materiais', 'description' => 'Gerenciar materiais de todos os professores'],
            'materials.publish' => ['module' => 'materiais', 'description' => 'Publicar sem aprovação'],
            'materials.review' => ['module' => 'materiais', 'description' => 'Aprovar ou devolver materiais'],
            'materials.bulk' => ['module' => 'materiais', 'description' => 'Executar operações em massa'],
            'analytics.view' => ['module' => 'relatorios', 'description' => 'Ver estatísticas'],
            'analytics.view_all' => ['module' => 'relatorios', 'description' => 'Ver estatísticas de toda a plataforma'],
            'cast.update' => ['module' => 'elenco', 'description' => 'Editar perfis do Elenco'],
            'home.manage' => ['module' => 'home', 'description' => 'Montar e publicar a home'],
            'categories.manage' => ['module' => 'catalogo', 'description' => 'Gerenciar categorias e matérias'],
            'students.manage' => ['module' => 'pessoas', 'description' => 'Gerenciar alunos e turmas'],
            'teachers.manage' => ['module' => 'pessoas', 'description' => 'Gerenciar professores'],
            'announcements.manage' => ['module' => 'comunicacao', 'description' => 'Gerenciar avisos'],
            'notifications.send' => ['module' => 'comunicacao', 'description' => 'Enviar notificações'],
            'live.manage' => ['module' => 'olavo_tv', 'description' => 'Gerenciar transmissões'],
            'live.keys.view' => ['module' => 'olavo_tv', 'description' => 'Ver chaves de transmissão'],
            'settings.manage' => ['module' => 'sistema', 'description' => 'Alterar configurações gerais'],
            'roles.manage' => ['module' => 'sistema', 'description' => 'Gerenciar papéis e permissões'],
            'audit.view' => ['module' => 'sistema', 'description' => 'Consultar logs de auditoria'],
        ];
    }

    public static function has(string $slug): bool
    {
        return array_key_exists($slug, self::all());
    }

    /** @return array<string, list<string>> */
    public static function defaultRoles(): array
    {
        return [
            'admin' => array_keys(self::all()),
            'teacher' => ['panel.teacher', 'content.view', 'materials.create', 'materials.update', 'analytics.view', 'cast.update'],
            'student' => ['content.view'],
        ];
    }
}
