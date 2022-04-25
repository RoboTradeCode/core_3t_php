<?php

namespace Src;

class Cross3T extends Main
{

    private array $config;

    /**
     * @param array $config Вся конфигурация приходящяя от агента
     */
    public function __construct(array $config)
    {

        $this->config = $config;

    }

    /**
     * Метод находит самые выгодные ордербуки со всех бирж
     *
     * @param array $route Треугольник, приходящи й от конфигуратора
     * @param array $balances Балансы, с разных бирж
     * @param array $orderbooks Ордербуки, с разных бирж
     * @return array Лучшие найденные ордербуки
     */
    public function findBestOrderbooks(array $route, array $balances, array $orderbooks): array
    {

        foreach ($route as $source) {

            $deal_amount_potential = $balances[$this->config['exchange']][$source['source_asset']]['free'] ?? $this->config['max_deal_amounts'][$source['source_asset']];

            $operation = ($source['operation'] == 'sell') ? 'bids' : 'asks';

            $potential_amounts = [];

            foreach ($orderbooks[$source['common_symbol']] as $exchange => $orderbook) {

                $amount = 0;

                if ($operation == 'bids') {

                    $base_asset_amount = 0;

                    foreach ($orderbook[$operation] as $price_and_amount) {

                        if (($base_asset_amount + $price_and_amount[1]) < $deal_amount_potential) {

                            $amount += $price_and_amount[0] * $price_and_amount[1];

                            $base_asset_amount += $price_and_amount[1];

                        } else {

                            $amount += $price_and_amount[0] * ($deal_amount_potential - $base_asset_amount);

                            break;

                        }

                    }

                } else {

                    $quote_asset_amount = 0;

                    foreach ($orderbook[$operation] as $price_and_amount) {

                        if (($quote_asset_amount + $price_and_amount[0] * $price_and_amount[1]) < $deal_amount_potential) {

                            $amount += $price_and_amount[1];

                            $quote_asset_amount += $price_and_amount[0] * $price_and_amount[1];

                        } else {

                            $amount += ($deal_amount_potential - $quote_asset_amount) / $price_and_amount[0];

                            break;

                        }

                    }

                }

                $potential_amounts[$exchange] = $amount;

            }

            $best_exchange = array_keys($potential_amounts, max($potential_amounts))[0];

            $best_orderbooks[$source['common_symbol']] = [
                $operation => $orderbooks[$source['common_symbol']][$best_exchange][$operation],
                'exchange' => $best_exchange
            ];

        }

        return $best_orderbooks ?? [];

    }

    /**
     * Фильтрует баланс, чтобы он был в диапазоне min_deal_amount и max_deal_amount
     *
     * @param array $balances Балансы со всех бирж, взятые из memcached
     * @return void
     */
    public function filterBalanceByMinAndMAxDealAmount(array &$balances)
    {

        foreach ($balances as $exchange => $balance) {

            foreach ($balance as $asset => $value) {

                if ($value['free'] <= $this->config['min_deal_amounts'][$asset]) {

                    unset($balances[$exchange][$asset]);

                } elseif ($value['free'] >= $this->config['max_deal_amounts'][$asset]) {

                    $balances[$exchange][$asset]['free'] = $this->config['max_deal_amounts'][$asset];

                }

            }

        }

    }

    /**
     * Возвращает данные из memcached в определенном формате и отделенные по ордербукам, балансам и т. д.
     *
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return array[]
     */
    public function reformatAndSeparateData(array $memcached_data): array
    {

        foreach ($memcached_data as $key => $data) {

            if (isset($data)) {

                $parts = explode('_', $key);

                $exchange = $parts[0];
                $action = $parts[1];
                $value = $parts[2] ?? null;

                if ($action == 'balances') {
                    $balances[$exchange] = $data;
                } elseif ($action == 'orderbook' && $value) {
                    $orderbooks[$value][$exchange] = $data;
                } else {
                    $undefined[$key] = $data;
                }

            }

        }

        return [
            'balances' => $balances ?? [],
            'orderbooks' => $orderbooks ?? [],
            'undefined' => $undefined ?? [],
        ];

    }

    /**
     * Проверяет пришел ли новый конфиг и обновляет текущий на новый
     *
     * @param array $config Текущая конфигурация
     * @param array $memcached_data Сырые данные, взятые напрямую из memcached
     * @return bool
     */
    public function proofConfigOnUpdate(array &$config, array &$memcached_data): bool
    {

        if (isset($memcached_data['config'])) {

            $config = $memcached_data['config'];

            unset($memcached_data['config']);

            echo '[Ok] Config is update' . PHP_EOL;

            return true;

        }

        return false;

    }

    public function getAllMemcachedKeys(): array
    {

        $keys = [];

        foreach ($this->config['exchanges'] as  $exchange)
            $keys = array_merge(
                $keys,
                preg_filter(
                    '/^/',
                    $exchange . '_orderbook_',
                    array_column($this->config['markets'], 'common_symbol')
                ),
                [$exchange . '_balances'], // добавить еще к массиву ключ баланса
                [$exchange . '_orders'] // добавить еще к массиву ключ для получения ордеров
            );

        return $keys;

    }

    public function getOrderbook(array $combinations, array $best_orderbooks): array
    {

        foreach (
            ['step_one' => 'step_one_symbol', 'step_two' => 'step_two_symbol', 'step_three' => 'step_three_symbol'] as $step => $step_symbol
        ) {

            foreach ($this->config['markets'] as $market) {

                if ($market['common_symbol'] == $combinations[$step_symbol]) {

                    $market_config = $market;

                    break;

                }

            }

            $orderbook[$step] = [
                'bids' => $best_orderbooks[$combinations[$step_symbol]]['bids'] ?? [],
                'asks' => $best_orderbooks[$combinations[$step_symbol]]['asks'] ?? [],
                'symbol' => $combinations[$step_symbol],
                'limits' => $market_config['limits'] ?? [],
                'price_increment' => $market_config['price_increment'] ?? 0,
                'amount_increment' => $market_config['amount_increment'] ?? 0,
                'amountAsset' => $market_config['assets']['base'] ?? '',
                'priceAsset' => $market_config['assets']['quote'] ?? '',
                'exchange' => $best_orderbooks[$combinations[$step_symbol]]['exchange'] ?? '',
                'fee' => $config['fees'][$best_orderbooks[$combinations[$step_symbol]]['exchange']] ?? 0,
            ];

        }

        return $orderbook ?? [];

    }

}