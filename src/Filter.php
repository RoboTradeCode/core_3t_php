<?php

namespace Src;

class Filter
{

    public static function routeOnlyDirectAndReverse(array &$routes): array
    {

        foreach ($routes as $key => $route) {

            $triangle_assets = array_column($route, 'source_asset');

            asort($triangle_assets);

            $triangle = implode('-', $triangle_assets);

            if (isset($new_routes[$triangle])) {

                $new_routes[$triangle] = array_filter(
                    $new_routes[$triangle],
                    function($new_route) use (&$routes, $key, $route) {
                        if (
                            isset($new_route[$route[0]['common_symbol']]) &&
                            isset($new_route[$route[1]['common_symbol']]) &&
                            isset($new_route[$route[2]['common_symbol']]) &&
                            $new_route[$route[0]['common_symbol']] == $route[0]['operation'] &&
                            $new_route[$route[1]['common_symbol']] == $route[1]['operation'] &&
                            $new_route[$route[2]['common_symbol']] == $route[2]['operation']
                        ) {

                            unset($routes[$key]);

                            return false;

                        }

                        return true;
                    }
                );

            }

            $new_routes[$triangle][] = [
                $route[0]['common_symbol'] => $route[0]['operation'],
                $route[1]['common_symbol'] => $route[1]['operation'],
                $route[2]['common_symbol'] => $route[2]['operation']
            ];

        }

        return $new_routes ?? [];

    }
    
}