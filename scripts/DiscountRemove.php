<?php

/**
 * Файл из репозитория MikBill-EventSystem-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-kit
 */

declare(strict_types=1);

/**
 * Путь к конфигурационному файлу MikBill
 * @link https://wiki.mikbill.pro/billing/config_file
 */
const CONFIG = '/var/www/mikbill/admin/app/etc/config.xml';

/** Наименования параметров скидок в БД */
const DISCOUNT_OPTIONS = [
    'ext_discount_extended',
    'ext_discount_global',
    'ext_discount_global_fixed',
    'ext_discount_packet',
    'ext_discount_packet_fixed',
    'ext_discount_subs',
    'ext_discount_subs_fixed',
    'ext_discount_device',
    'ext_discount_device_fixed'
];

require_once 'lib/func/getConfig.php';
require_once 'lib/func/getConnect.php';

/**
 * @return array|null Параметры клиента/скидок
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`uid`,
            `users`.`gid`,
            `ext_discount_extended`.`key`,
            `ext_discount_extended`.`value`,
            `ext_discount_global`.`key`,
            `ext_discount_global`.`value`,
            `ext_discount_global_fixed`.`key`,
            `ext_discount_global_fixed`.`value`,
            `ext_discount_packet`.`key`,
            `ext_discount_packet`.`value`,
            `ext_discount_packet_fixed`.`key`,
            `ext_discount_packet_fixed`.`value`,
            `ext_discount_subs`.`key`,
            `ext_discount_subs`.`value`,
            `ext_discount_subs_fixed`.`key`,
            `ext_discount_subs_fixed`.`value`,
            `ext_discount_device`.`key`,
            `ext_discount_device`.`value`,
            `ext_discount_device_fixed`.`key`,
            `ext_discount_device_fixed`.`value`
        FROM
            `users`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountExtended
            ) AS
                `ext_discount_extended`
                    ON
                        `ext_discount_extended`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountGlobal
            ) AS
                `ext_discount_global`
                    ON
                        `ext_discount_global`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountGlobalFixed
            ) AS
                `ext_discount_global_fixed`
                    ON
                        `ext_discount_global_fixed`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountPacket
            ) AS
                `ext_discount_packet`
                    ON
                        `ext_discount_packet`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountPacketFixed
            ) AS
                `ext_discount_packet_fixed`
                    ON
                        `ext_discount_packet_fixed`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountSubs
            ) AS
                `ext_discount_subs`
                    ON
                        `ext_discount_subs`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountSubsFixed
            ) AS
                `ext_discount_subs_fixed`
                    ON
                        `ext_discount_subs_fixed`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountDevice
            ) AS
                `ext_discount_device`
                    ON
                        `ext_discount_device`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`key`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = :extDiscountDeviceFixed
            ) AS
                `ext_discount_device_fixed`
                    ON
                        `ext_discount_device_fixed`.`uid` = `users`.`uid`
        WHERE
            `users`.`uid` = :uId");

    $sth->bindValue(':extDiscountExtended', DISCOUNT_OPTIONS[0]);
    $sth->bindValue(':extDiscountGlobal', DISCOUNT_OPTIONS[1]);
    $sth->bindValue(':extDiscountGlobalFixed', DISCOUNT_OPTIONS[2]);
    $sth->bindValue(':extDiscountPacket', DISCOUNT_OPTIONS[3]);
    $sth->bindValue(':extDiscountPacketFixed', DISCOUNT_OPTIONS[4]);
    $sth->bindValue(':extDiscountSubs', DISCOUNT_OPTIONS[5]);
    $sth->bindValue(':extDiscountSubsFixed', DISCOUNT_OPTIONS[6]);
    $sth->bindValue(':extDiscountDevice', DISCOUNT_OPTIONS[7]);
    $sth->bindValue(':extDiscountDeviceFixed', DISCOUNT_OPTIONS[8]);
    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_NAMED);

    return ($result !== false) ? $result : null;
}

/**
 * @param string $uId ID пользователя
 * @param string $key Наименование параметра скикдки
 */
function removeDiscount(string $uId,
                        string $key): void
{
    static $sth;

    $sth = $sth ?? getConnect()->prepare("
        DELETE
        FROM
            `users_custom_fields`
        WHERE
            `users_custom_fields`.`uid` = :uId
                AND
            `users_custom_fields`.`key` = :key");

    $sth->bindParam(':uId', $uId);
    $sth->bindParam(':key', $key);

    $sth->execute();
}

/**
 * @param string $uId ID пользователя
 * @param string $gId ID тарифного плана
 * @param string $valueName Наименование параметра
 * @param string $oldValue Старое значение параметра
 * @param string|null $newValue Новое значение параметра
 */
function logEvent(string $uId,
                  string $gId,
                  string $valueName,
                  string $oldValue,
                  string $newValue = null): void
{
    static $sth;

    $sth = $sth ?? getConnect()->prepare("
        INSERT INTO
            `logs` (
                `stuffid`,
                `date`,
                `logtypeid`,
                `uid`,
                `gid`,
                `valuename`,
                `oldvalue`,
                `newvalue`
            )
        VALUES (
            0,
            NOW(),
            1,
            :uId,
            :gId,
            :valueName,
            :oldValue,
            :newValue
        )");

    $sth->bindParam(':uId', $uId);
    $sth->bindParam(':gId', $gId);
    $sth->bindParam(':valueName', $valueName);
    $sth->bindParam(':oldValue', $oldValue);
    $sth->bindParam(':newValue',
        $newValue, (!is_null($newValue))
            ? PDO::PARAM_STR
            : PDO::PARAM_NULL);

    $sth->execute();
}

try {
    !is_null($client = getClient()) || exit;

    getConnect()->beginTransaction() || exit(
        "Begin a transaction failed\n");
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

foreach (DISCOUNT_OPTIONS as $v)
    if (($key = array_search($v, $client['key'], true)) !== false)
        try {
            removeDiscount($client['uid'], $v);
            logEvent($client['uid'],
                $client['gid'],
                $v,
                $client['value'][$key]);
        } catch (PDOException $e) {
            try {
                getConnect()->rollBack() || exit(
                    "Rollback a transaction failed\n");
            } catch (PDOException $e) {
                exit(sprintf("%s\n", $e->getMessage()));
            }

            exit(sprintf("%s\n", $e->getMessage()));
        }

try {
    getConnect()->commit() || exit(
        "Commit a transaction failed\n");
} catch (PDOException $e) {
    try {
        getConnect()->rollBack() || exit(
            "Rollback a transaction failed\n");
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}
