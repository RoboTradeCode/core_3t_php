<?php

namespace Src;

class OrderBookCorrect
{

    public static function beforeRealCreateOrder(array &$orderbook, string $type, string $side, float $amount, float $price = null): void
    {

        if ($type == 'market') {

            $bid_or_ask = ($side == 'sell') ? 'bids' : 'asks';

            foreach ($orderbook[$bid_or_ask] as $depth => $price_and_amount) {

                $orderbook[$bid_or_ask][$depth][1] -= round($amount, 8);

                if ($orderbook[$bid_or_ask][$depth][1] < 0) {

                    $orderbook[$bid_or_ask][$depth][1] = 0;

                    $amount -= $price_and_amount[1];

                    continue;

                }

                break;

            }

        } elseif ($type == 'limit') {

            $is_sell = ($side == 'sell');

            $bid_or_ask = $is_sell ? 'bids' : 'asks';

            foreach ($orderbook[$bid_or_ask] as $depth => $price_and_amount) {

                if (
                    ($is_sell && $price_and_amount[0] >= $price) ||
                    (!$is_sell && $price_and_amount[0] <= $price) ||
                    FloatRound::compare($price_and_amount[0], $price)
                ) {

                    $orderbook[$bid_or_ask][$depth][1] -= round($amount, 8);

                    if ($orderbook[$bid_or_ask][$depth][1] < 0) {

                        $orderbook[$bid_or_ask][$depth][1] = 0;

                        $amount -= $price_and_amount[1];

                        continue;

                    } else
                        $amount = 0;

                }

                break;

            }

            if ($amount != 0) {

                $bid_or_ask = $is_sell ? 'asks' : 'bids';

                foreach ($orderbook[$bid_or_ask] as $depth => $price_and_amount) {

                    if (FloatRound::compare($price_and_amount[0], $price)) {

                        $orderbook[$bid_or_ask][$depth][1] += round($amount, 8);

                        break;

                    }

                    if (($is_sell && $price_and_amount[0] > $price) || (!$is_sell && $price_and_amount[0] < $price)) {

                        $orderbook[$bid_or_ask][$depth] = (isset($next_depth)) ? $next_depth : [$price, $amount];

                        $next_depth = $price_and_amount;

                    }

                }

            }

        }

    }

    public static function beforeRealCancelOrder(array &$orderbook, float $amount, float $price): void
    {

        foreach ($orderbook['bids'] as $depth => $bid_price_and_amount) {

            if (FloatRound::compare($bid_price_and_amount[0], $price)) {

                $remainder = $orderbook['bids'][$depth][1] - $amount;

                if ($remainder >= 0)
                    $orderbook['bids'][$depth][1] = round($remainder, 8);

                break;

            }

            if (FloatRound::compare($orderbook['asks'][$depth][0], $price)) {

                $remainder = $orderbook['asks'][$depth][1] - $amount;

                if ($remainder >= 0)
                    $orderbook['asks'][$depth][1] = round($remainder, 8);

                break;

            }

        }

    }

}