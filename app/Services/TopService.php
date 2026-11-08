<?php
namespace App\Services;

class TopService
{
    private BridgeApi $bridge;

    public function __construct(?BridgeApi $bridge = null)
    {
        $this->bridge = $bridge ?? new BridgeApi();
    }

    /**
     * Карта режимов → подрежимы → endpoint/колонки.
     * Если у режима один подрежим — кнопки на UI не показываем.
     */
    private function map(): array
    {
        $duelCols = [
            'player_name' => 'Игрок',
            'wins'        => 'Победы',
            'losses'      => 'Поражения',
            'kills'       => 'Убийства',
            'deaths'      => 'Смерти',
        ];

        $duelColsShort = [
            'player_name' => 'Игрок',
            'wins'        => 'Победы',
            'losses'      => 'Поражения',
            'draws'       => 'Ничья',
            'hits'      => 'Удары',
        ];

        return [
            'bedwars' => [
                'label'   => 'BedWars',
                'default' => 'overall',
                'modes'   => [
                    'overall' => [
                        'label'    => 'Общий',
                        'endpoint' => 'bw-top',
                        'orderBy'  => 'rating',
                        'columns'  => [
                            'player_name'    => 'Игрок',
                            'kills'          => 'Убийства',
                            'deaths'         => 'Смерти',
                            'wins'           => 'Победы',
                            'losses'         => 'Поражения',
                            'beds_destroyed' => 'Кровати',
                            'rating'         => 'Рейтинг',
                        ],
                    ],
                ],
            ],

            'tntrun' => [
                'label'   => 'BedWars',
                'default' => 'overall',
                'modes'   => [
                    'overall' => [
                        'label'    => 'Общий',
                        'endpoint' => 'bw-top',
                        'orderBy'  => 'rating',
                        'columns'  => [
                            'player_name'    => 'Игрок',
                            'kills'          => 'Убийства',
                            'deaths'         => 'Смерти',
                            'wins'           => 'Победы',
                            'losses'         => 'Поражения',
                            'beds_destroyed' => 'Кровати',
                            'rating'         => 'Рейтинг',
                        ],
                    ],
                ],
            ],

            'duels' => [
                'label'   => 'Duels',
                'default' => 'classic',
                'modes'   => [
                    'classic' => [
                        'label'    => 'Classic',
                        'endpoint' => 'classic-top',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                    'uhc' => [
                        'label'    => 'UHC',
                        'endpoint' => 'uhc-top',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                    'bow' => [
                        'label'    => 'Bow',
                        'endpoint' => 'bow-top',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                    'boxing' => [
                        'label'    => 'Boxing',
                        'endpoint' => 'boxing-top',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                    'sumo' => [
                        'label'    => 'Sumo',
                        'endpoint' => 'sumo-top',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                    'bridge' => [
                        'label'    => 'The Bridge',
                        'endpoint' => 'bridge-solo',
                        'orderBy'  => 'wins',
                        'columns'  => $duelColsShort,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   config: array{endpoint:string,orderBy:string,columns:array}|null,
     *   modes: array<string, array{label:string,endpoint:string,orderBy:string,columns:array}>,
     *   activeMode: string|null,
     *   gameKey: string|null
     * }
     */
    public function resolve(?array $gameInfo, string $slug, ?string $mode = null): array
    {
        $empty = [
            'config'     => null,
            'modes'      => [],
            'activeMode' => null,
            'gameKey'    => null,
        ];

        $map = $this->map();
        $name = mb_strtolower(trim($gameInfo['name'] ?? $slug));
        $name = str_replace(['_', '-'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        $gameKey = null;
        foreach (array_keys($map) as $key) {
            if ($name === $key || str_replace(' ', '', $name) === str_replace(' ', '', $key)) {
                $gameKey = $key;
                break;
            }
        }

        // Старые названия страниц вроде «Bow Duel»
        if ($gameKey === null) {
            $duelAliases = [
                'bow duel'     => 'bow',
                'classic duel' => 'classic',
                'uhc duel'     => 'uhc',
                'boxing duel'  => 'boxing',
                'sumo duel'    => 'sumo',
                'the bridge'   => 'bridge',
                'bridge'       => 'bridge',
            ];
            if (isset($duelAliases[$name])) {
                $gameKey = 'duels';
                $mode = $mode ?: $duelAliases[$name];
            }
        }

        if ($gameKey === null) {
            if (str_contains($name, 'bedwar')) {
                $gameKey = 'bedwars';
            } elseif (str_contains($name, 'duel')) {
                $gameKey = 'duels';
            }
        }

        if ($gameKey === null || empty($map[$gameKey]['modes'])) {
            return $empty;
        }

        $modes   = $map[$gameKey]['modes'];
        $default = $map[$gameKey]['default'] ?? array_key_first($modes);
        $active  = $mode !== null && $mode !== '' ? mb_strtolower($mode) : $default;

        if (!isset($modes[$active])) {
            $active = $default;
        }

        $cfg = $modes[$active];

        return [
            'config' => [
                'endpoint' => $cfg['endpoint'],
                'orderBy'  => $cfg['orderBy'],
                'columns'  => $cfg['columns'],
            ],
            'modes'      => $modes,
            'activeMode' => $active,
            'gameKey'    => $gameKey,
        ];
    }

    /** Обратная совместимость */
    public function resolveConfig(?array $gameInfo, string $slug): ?array
    {
        return $this->resolve($gameInfo, $slug)['config'];
    }

    public function fetch(string $endpoint, array $params = []): array
    {
        $defaults = [
            'page'    => 1,
            'limit'   => 20,
            'orderBy' => null,
            'order'   => 'desc',
        ];

        $result = $this->bridge->getTop($endpoint, array_merge($defaults, $params));

        if ($result === null) {
            return [
                'rows' => [],
                'meta' => ['page' => 1, 'pages' => 1, 'total' => 0, 'limit' => 20],
            ];
        }

        return $result;
    }
}