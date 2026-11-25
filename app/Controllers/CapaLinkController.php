<?php

namespace App\Controllers;

use App\Libraries\Capa;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Public (token-authenticated, no login required) multi-card CAPA submission portal.
 * 1 unique link can hold N failed line cards (e.g. 5 cards).
 * Submitting 1 card locks ONLY that card as view-only while other draft/unset cards remain editable.
 */
class CapaLinkController extends BaseController
{
    public function page(string $token): ResponseInterface|string
    {
        $data = Capa::linkData($token);
        if (! $data) {
            return $this->response->setStatusCode(404)->setBody(view('quality/capa_public', [
                'invalid' => true,
                'token'   => $token,
                'link'    => null,
                'cases'   => [],
            ]));
        }

        return view('quality/capa_public', [
            'invalid' => false,
            'token'   => $token,
            'link'    => $data['link'],
            'cases'   => $data['cases'],
        ]);
    }

    public function save(string $token): ResponseInterface
    {
        $p      = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $capaId = (int) ($p['capa_id'] ?? 0);
        $submit = ! empty($p['submit']);
        $res    = Capa::saveFromLink($token, $capaId, $p, $submit);

        return $this->response->setStatusCode($res['ok'] ? 200 : 422)->setJSON($res);
    }

    public function uploadPhoto(string $token): ResponseInterface
    {
        $data = Capa::linkData($token);
        if (! $data) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Invalid CAPA link.']);
        }
        $capaId = (int) ($this->request->getPost('capa_id') ?? 0);
        if (! in_array($capaId, array_column($data['cases'], 'id'), true)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Card not in this CAPA link.']);
        }
        $actor = trim((string) ($this->request->getPost('prepared_by') ?? '')) ?: 'Line Staff (Link)';
        $file  = $this->request->getFile('photo');
        $res   = Capa::uploadPhoto($capaId, $file, $actor, 'link', $token);

        return $this->response->setStatusCode($res['ok'] ? 200 : 422)->setJSON($res);
    }

    public function deletePhoto(string $token): ResponseInterface
    {
        $data = Capa::linkData($token);
        if (! $data) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Invalid CAPA link.']);
        }
        $p       = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $capaId  = (int) ($p['capa_id'] ?? 0);
        $photoId = (int) ($p['photo_id'] ?? 0);
        if (! in_array($capaId, array_column($data['cases'], 'id'), true)) {
            return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Card not in this CAPA link.']);
        }
        $actor = trim((string) ($p['prepared_by'] ?? '')) ?: 'Line Staff (Link)';
        $res   = Capa::removePhoto($capaId, $photoId, $actor, true);

        return $this->response->setStatusCode($res['ok'] ? 200 : 422)->setJSON($res);
    }

    public function servePhoto(string $token, int $photoId): ResponseInterface
    {
        $data = Capa::linkData($token);
        if (! $data) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $allowedIds = array_column($data['cases'], 'id');
        $db         = \Config\Database::connect();
        $p          = $db->table('tbl_dtr_capa_photos')->where(['id' => $photoId, 'deleted_at' => null])->get()->getRowArray();
        if (! $p || ! in_array((int) $p['capa_id'], $allowedIds, true)) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'capa' . DIRECTORY_SEPARATOR . (int) $p['capa_id'] . DIRECTORY_SEPARATOR . basename((string) $p['filename']);
        if (! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }

        return $this->response
            ->setHeader('Content-Type', (string) ($p['mime'] ?: 'image/jpeg'))
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody((string) file_get_contents($path));
    }
}
