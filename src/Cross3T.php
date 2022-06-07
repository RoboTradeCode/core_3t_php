<?php

namespace Src;

class Cross3T extends Main
{

    private array $config;
    public array $common_config;
    private int $interation;
    private DiscreteTime $discret_time;

    /**
     * @param array $config Вся конфигурация приходящяя от агента
     */
    public function __construct(array $config, array $common_config)
    {

        $this->config = $config;

        $this->common_config = $common_config;

        $this->discret_time = new DiscreteTime();

        $this->interation = 0;

    }

    /**
     * Запуск алгоритма просчета cross_3t
     *
     * @param array $balances Балансы, с разных бирж
     * @param array $orderbooks Ордербуки, с разных бирж
     * @return array Возвращает результат
     */
    public function run(array $balances, array $orderbooks, bool $multi = false): array
    {

        foreach ($this->config['routes'] as $route) {

            $combinations = $this->getCombinations($route);

            Debug::rec($route, 'Route');
            Debug::rec($combinations, 'Combinations');

            if ($best_orderbooks = $this->findBestOrderbooks($route, $balances, $orderbooks)) {

                Debug::rec($best_orderbooks, 'Best Orderbooks');

                if ($orderbook = $this->getOrderbook($combinations, $best_orderbooks, $multi)) {

                    Debug::rec($orderbook, '$this->getOrderbook function return');

                    $results[] = $this->getResults(
                        $this->config['max_deal_amounts'][$combinations['main_asset_name']],
                        $this->config['max_depth'],
                        $this->config['rates'],
                        $combinations,
                        $orderbook,
                        [
                            $combinations['main_asset_name'] => $balances[$orderbook['step_one']['exchange']][$combinations['main_asset_name']],
                            $combinations['asset_one_name'] => $balances[$orderbook['step_two']['exchange']][$combinations['asset_one_name']],
                            $combinations['asset_two_name'] => $balances[$orderbook['step_three']['exchange']][$combinations['asset_two_name']],
                        ]
                    );

                    Debug::rec($results, 'Get Results');
                    Debug::rec(
                        [
                            $combinations['main_asset_name'] => $balances[$orderbook['step_one']['exchange']][$combinations['main_asset_name']],
                            $combinations['asset_one_name'] => $balances[$orderbook['step_two']['exchange']][$combinations['asset_one_name']],
                            $combinations['asset_two_name'] => $balances[$orderbook['step_three']['exchange']][$combinations['asset_two_name']],
                        ],
                        'Balances to get Result'
                    );
                    //Debug::recordToFile(true);

                }


            }

        }

        if (isset($results)) {

            $best_result = $this->getBestResult($results, $this->config['min_profit']);

            if (isset($this->common_config['debug']) && $this->common_config['debug'] && $this->discret_time->proof()) {

                $this->madeHtmlVision($results, $best_result, $orderbooks, $balances, $this->common_config['made_html_vision_file']);

            }

            $this->interation++;

            return $best_result;

        }

        return [];

    }

    public function getInteration(): int
    {

        return $this->interation;

    }

    /**
     * Фильтрует баланс, чтобы он был в диапазоне min_deal_amount и max_deal_amount
     *
     * @param array $balances Балансы со всех бирж, взятые из memcached
     * @return void
     */
    public function filterBalanceByMinAndMAxDealAmount(array &$balances): void
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

    /**
     * Возвращает самый лучший результат
     *
     * @param array $results Результаты
     * @param array $min_profit Минимальная прибыль в main_asset_name
     * @return array Лучший результат
     */
    private function getBestResult(array $results, array $min_profit): array
    {

        foreach (array_column($results, 'results') as $items)
            foreach ($items as $item)
                $all_results[] = $item;

        if (isset($all_results)) {

            $array = array_column($all_results, 'result_in_main_asset');

            $best_result = $all_results[array_keys($array, max($array))[0]];

            if ($best_result["result"] > $min_profit[$best_result['main_asset_name']])
                return $best_result;

        }

        return [];

    }

    /**
     * Метод находит самые выгодные ордербуки со всех бирж
     *
     * @param array $route Треугольник, приходящи й от конфигуратора
     * @param array $balances Балансы, с разных бирж
     * @param array $orderbooks Ордербуки, с разных бирж
     * @return array Лучшие найденные ордербуки
     */
    private function findBestOrderbooks(array $route, array $balances, array $orderbooks): array
    {

        $best_orderbooks = [];

        foreach ($route as $source) {

            $deal_amount_potential = $this->config['max_deal_amounts'][$source['source_asset']];

            $operation = ($source['operation'] == 'sell') ? 'bids' : 'asks';

            $potential_amounts = [];

            // если не существует такого ордербука, возвращай пустой массив
            if (!isset($orderbooks[$source['common_symbol']]))
                return [];

            foreach ($orderbooks[$source['common_symbol']] as $exchange => $orderbook) {

                if (isset($balances[$exchange][$source['source_asset']])) {

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

            }

            if ($potential_amounts) {

                $best_exchange = array_keys($potential_amounts, max($potential_amounts))[0];

                $best_orderbooks[$source['common_symbol']] = [
                    $operation => $orderbooks[$source['common_symbol']][$best_exchange][$operation],
                    'exchange' => $best_exchange
                ];

            }

        }

        return (count($best_orderbooks) == 3) ? $best_orderbooks : [];

    }

    /**
     * Возвращает комбинацию для использования переменной в getResults()
     *
     * @param array $route Маршрут от конфигуратора в одном треугольнике
     * @return array Массив комбинации
     */
    private function getCombinations(array $route): array
    {

        $step_one = array_shift($route);
        $step_two = array_shift($route);
        $step_three = array_shift($route);

        return [
            'main_asset_name' => $step_one['source_asset'],
            'main_asset_amount_precision' => 0.00000001,
            'asset_one_name' => $step_two['source_asset'],
            'asset_two_name' => $step_three['source_asset'],
            'step_one_symbol' => $step_one['common_symbol'],
            'step_two_symbol' => $step_two['common_symbol'],
            'step_three_symbol' => $step_three['common_symbol'],
        ];

    }

    /**
     * Возвращает три ордербука для каждого шага, чтобы использовать его в getResults
     *
     * @param array $combinations Комбинация для каждого шага в треугольнике
     * @param array $best_orderbooks Лучшие ордербуки
     * @return array Три шага ордербука
     */
    private function getOrderbook(array $combinations, array $best_orderbooks, bool $multi): array
    {

        foreach (
            ['step_one' => 'step_one_symbol', 'step_two' => 'step_two_symbol', 'step_three' => 'step_three_symbol'] as $step => $step_symbol
        ) {

            if (isset($best_orderbooks[$combinations[$step_symbol]]['exchange'])) {

                $markets = $multi
                    ? $this->config['markets'][$best_orderbooks[$combinations[$step_symbol]]['exchange']]
                    : $this->config['markets'];

                foreach ($markets as $market) {

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
                    'amountAsset' => $market_config['base_asset'] ?? '',
                    'priceAsset' => $market_config['quote_asset'] ?? '',
                    'exchange' => $best_orderbooks[$combinations[$step_symbol]]['exchange'],
                    'fee' => $this->config['fees'][$best_orderbooks[$combinations[$step_symbol]]['exchange']],
                ];

            } else {

                return [];

            }

        }

        return $orderbook ?? [];

    }

}