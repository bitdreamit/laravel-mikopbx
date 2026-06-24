<?php
namespace BitDreamIT\MikoPBX\Services;

class IVRService
{
    public function __construct(private RestApiService $api) {}

    public function getMenus(): array { return $this->api->getIVRMenus(); }

    public function save(array $node): array { return $this->api->saveIVRMenu($node); }

    /** Build a simple IVR tree array from a node definition */
    public function buildTree(string $greeting, array $keys): array
    {
        return [
            'greeting' => $greeting,
            'timeout'  => 5,
            'retries'  => 3,
            'options'  => array_map(fn($k, $v) => [
                'digit'  => $k,
                'action' => $v['action'] ?? 'extension',
                'target' => $v['target'] ?? '',
                'label'  => $v['label'] ?? "Press {$k}",
            ], array_keys($keys), $keys),
        ];
    }
}
