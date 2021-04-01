<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-php-kit
 */

declare(strict_types=1);

/**
 * Путь к конфигурационному файлу MikBill
 * @link https://wiki.mikbill.pro/billing/config_file
 */
const CONFIG = '/var/www/mikbill/admin/app/etc/config.xml';

/**
 * Тип NAS RouterOS
 * @link https://wiki.mikbill.pro/billing/nas_access_server/mikbillnas
 */
const NAS_TYPE = 'mikrotik';

require_once 'lib/func/getConfig.php';
require_once 'lib/func/getConnect.php';
require_once 'lib/func/getNas.php';
require_once '../../../autoload.php';

/**
 * @return string|null Параметры клиента
 */
function getClient(): ?string
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`user`
        FROM
            `users`
        LEFT JOIN
            `bugh_plategi_stat`
                ON
                    `bugh_plategi_stat`.`uid` = `users`.`uid`
                        AND
                    `bugh_plategi_stat`.`date` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `bugh_plategi_stat`.`bughtypeid` NOT IN
                    (
                        1, 2, 6, 7, 9, 10, 15, 16, 17, 18, 20, 21,
                        22, 23, 24, 25, 26, 27, 29, 30, 32, 33, 34,
                        35, 36, 39, 42, 43, 46, 48, 49, 50, 51, 64,
                        65, 72, 73, 74, 75, 78, 79, 93, 99, 100, 103,
                        104, 105
                    )
        WHERE
            `users`.`state` = 1
                AND 
            `users`.`uid` = :uId
                AND
            `users`.`blocked` = 0
                AND
            `users`.`date_abonka` = DAY(
                NOW()
            )
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");
    
    $sth->bindParam(':uId', $_SERVER['argv'][1]);
    
    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_COLUMN);

    return ($result !== false) ? $result : null;
}

try {
    !is_null($client = getClient()) || exit;
    !is_null($nas = getNas()) || exit;
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

foreach ($nas as $v)
    try {
        $c = new RouterOS\Client(['host' => $v['nasname'],
            'user' => $v['naslogin'],
            'pass' => $v['naspass']]);

        $response = $c->query((new RouterOS\Query('/ppp/active/print'))
            ->where('name', $client))
            ->read();

        if (!empty($response[0]['.id']))
            $c->query((new RouterOS\Query('/ppp/active/remove'))
                ->equal('.id', $response[0]['.id']))
                ->read();
    } catch (
        RouterOS\Exceptions\BadCredentialsException
        | RouterOS\Exceptions\ConnectException
        | RouterOS\Exceptions\ClientException
        | RouterOS\Exceptions\ConfigException
        | RouterOS\Exceptions\QueryException $e
    ) {
        echo sprintf("%s\n", $e->getMessage());
    }
