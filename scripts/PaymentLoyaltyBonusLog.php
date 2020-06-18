<?php

/**
 * Файл из репозитория MikBill-EventSystem-PHP-Kit
 * @link https://github.com/itpanda-llc
 */

/** Путь к конфигурационному файлу АСР MikBill */
const CONFIG = '../../../../www/mikbill/admin/app/etc/config.xml';

/**
 * Размер бонуса при длительности
 * пользования сервисами от одного года до двух лет
 * (Процент от стомиости тарифного плана)
 */
const FIRST_YEAR_BONUS_PERCENT = 3;

/**
 * Размер бонуса при длительности
 * пользования сервисами от двух до трех лет
 * (Процент от стомиости тарифного плана)
 */
const SECOND_YEAR_BONUS_PERCENT = 5;

/**
 * Размер бонуса при длительности
 * пользования сервисами более трех лет
 * (Процент от стомиости тарифного плана)
 */
const THIRD_YEAR_BONUS_PERCENT = 10;

/** Наименьший размер начисляемого бонуса (Денежная единица) */
const BONUS_MIN_AMOUNT = 10;

/** Наибольший размер начисляемого бонуса (Денежная единица) */
const BONUS_MAX_AMOUNT = 100;

/** Номер категории платежа */
const CATEGORY_ID = -1;

/** Наименование категории платежа */
const CATEGORY_NAME = 'Бонус лояльности';

/** Комментарий к платежу */
const PAY_COMMENT = CATEGORY_NAME;

/**
 * @return SimpleXMLElement Объект конфигурационного файла
 */
function getConfig(): SimpleXMLElement
{
    static $sxe;

    if (!isset($sxe)) {
        try {
            $sxe = new SimpleXMLElement(CONFIG,
                LIBXML_ERR_NONE,
                true);
        } catch (Exception $e) {
            exit(sprintf("%s\n", $e->getMessage()));
        }
    }

    return $sxe;
}

/**
 * @return PDO Обработчик запросов к БД
 */
function getConnect(): PDO
{
    static $dbh;

    if (!isset($dbh)) {
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8",
            getConfig()->parameters->mysql->host,
            getConfig()->parameters->mysql->dbname);

        try {
            $dbh = new PDO($dsn,
                getConfig()->parameters->mysql->username,
                getConfig()->parameters->mysql->password,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            exit(sprintf("%s\n", $e->getMessage()));
        }
    }

    return $dbh;
}

/**
 * @return array|null Параметры клиента и бонуса
 */
function getClient(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`uid`,
            ROUND(
                IF(
                    (
                        @amount :=
                        FLOOR(
                            (
                                CASE
                                    WHEN
                                        DATE(
                                            NOW()
                                        ) >
                                        DATE(
                                            `date_fiz_contract_conclusion`.`value`
                                        ) + INTERVAL 3 YEAR
                                    THEN
                                        `packets`.`fixed_cost`
                                            *
                                        :thirdYearBonusPercent
                                            /
                                        100
                                    WHEN
                                        DATE(
                                            NOW()
                                        ) >
                                        DATE(
                                            `date_fiz_contract_conclusion`.`value`
                                        ) + INTERVAL 2 YEAR
                                    THEN
                                        `packets`.`fixed_cost`
                                            *
                                        :secondYearBonusPercent
                                            /
                                        100
                                    WHEN
                                        DATE(
                                            NOW()
                                        ) >
                                        DATE(
                                            `date_fiz_contract_conclusion`.`value`
                                        ) + INTERVAL 1 YEAR
                                    THEN
                                        `packets`.`fixed_cost`
                                            *
                                        :firstYearBonusPercent
                                            /
                                        100
                                END
                            ) / 10
                        ) * 10
                    ) < :bonusMinAmount,
                    :bonusMinAmount,
                    @amount
                ) , 2
            ) AS
                `amount`
        FROM
            `users`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_date_fiz_contract_conclusion'
            ) AS
                `date_fiz_contract_conclusion`
                    ON
                        `date_fiz_contract_conclusion`.`uid` = `users`.`uid`
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
        LEFT JOIN
            `addons_pay_api`
                ON
                    `addons_pay_api`.`user_ref` = `users`.`uid`
                        AND
                    `addons_pay_api`.`amount` > 0
                        AND
                    `addons_pay_api`.`category` < 0
                        AND
                    `addons_pay_api`.`update_time` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
                        AND
                    `addons_pay_api`.`status` = 1
        LEFT JOIN
            `packets`
                ON
                    `packets`.`gid` = `users`.`gid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_discount_global'
            ) AS
                `discount_global`
                    ON
                        `discount_global`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_discount_extended'
            ) AS
                `discount_extended`
                    ON
                        `discount_extended`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_discount_packet'
            ) AS
                `discount_packet`
                    ON
                        `discount_packet`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_discount_subs'
            ) AS
                `discount_subs`
                    ON
                        `discount_subs`.`uid` = `users`.`uid`
        LEFT JOIN
            (
                SELECT
                    `users_custom_fields`.`uid`,
                    `users_custom_fields`.`value`
                FROM
                    `users_custom_fields`
                WHERE
                    `key` = 'ext_discount_device'
            ) AS
                `discount_device`
                    ON
                        `discount_device`.`uid` = `users`.`uid`
        WHERE
            `users`.`uid` = :uId
                AND
            `users`.`sms_tel` IS NOT NULL
                AND
            `users`.`sms_tel` != ''
                AND
            DATE(
                NOW()
            ) >
            DATE(
                `date_fiz_contract_conclusion`.`value`
            ) + INTERVAL 1 YEAR
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `packets`.`fixed_cost`
                >
            `users`.`deposit` - `bugh_plategi_stat`.`summa`
                AND
            `packets`.`fixed_cost` <= `bugh_plategi_stat`.`summa`
                AND
            (
                `packets`.`fixed_cost` * LEAST(
                    :firstYearBonusPercent,
                    :secondYearBonusPercent,
                    :thirdYearBonusPercent)
                / 100
            ) >=
            :bonusMinAmount
                AND
            `users`.`deposit` - `bugh_plategi_stat`.`summa`
                >=
            0 - `packets`.`razresh_minus`
                AND
            `addons_pay_api`.`user_ref` IS NULL
                AND
            (
                (
                    (
                        `discount_global`.`value` IS NULL
                            OR
                        `discount_global`.`value` = '0'
                    )
                        AND
                    (
                        `discount_extended`.`value` IS NULL
                            OR
                        `discount_extended`.`value` = '0'
                    )
                )
                    OR
                (
                    `discount_extended`.`value` = '1'
                        AND
                    (
                        `discount_packet`.`value` IS NULL
                            OR
                        `discount_packet`.`value` = '0'
                    )
                        AND
                    (
                        `discount_subs`.`value` IS NULL
                            OR
                        `discount_subs`.`value` = '0'
                    )
                        AND
                    (
                        `discount_device`.`value` IS NULL
                            OR
                        `discount_device`.`value` = '0'
                    )
                )
            )
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");

    $sth->bindValue(':firstYearBonusPercent',
        FIRST_YEAR_BONUS_PERCENT,
        PDO::PARAM_INT);
    $sth->bindValue(':secondYearBonusPercent',
        SECOND_YEAR_BONUS_PERCENT,
        PDO::PARAM_INT);
    $sth->bindValue(':thirdYearBonusPercent',
        THIRD_YEAR_BONUS_PERCENT,
        PDO::PARAM_INT);
    $sth->bindValue(':bonusMinAmount',
        BONUS_MIN_AMOUNT,
        PDO::PARAM_INT);
    $sth->bindValue(':bonusMaxAmount',
        BONUS_MAX_AMOUNT,
        PDO::PARAM_INT);
    $sth->bindValue(':categoryId',
        CATEGORY_ID,
        PDO::PARAM_INT);
    $sth->bindParam(':uId', $_SERVER['argv'][1]);

    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

/**
 * @return bool Результат проверки категории платежа
 */
function checkCategory(): bool
{
    $sth = getConnect()->prepare("
        SELECT
            `addons_pay_api_category`.`category`
        FROM
            `addons_pay_api_category`
        WHERE
            `addons_pay_api_category`.`category` = :categoryId");

    $sth->bindValue(':categoryId', CATEGORY_ID, PDO::PARAM_INT);

    $sth->execute();

    return ($sth->rowCount() !== 0) ? true : false;
}

/** Добавление категории платежа */
function addCategory(): void
{
    $sth = getConnect()->prepare("
        INSERT INTO
            `addons_pay_api_category` (
                `category`,
                `categoryname`
            )
        VALUES (
            :categoryId,
            :categoryName
        )");

    $sth->bindValue(':categoryId', CATEGORY_ID, PDO::PARAM_INT);
    $sth->bindValue(':categoryName', CATEGORY_NAME);

    $sth->execute();
}

/**
 * @param string $userRef uId
 * @param string $amount Размер бонуса
 */
function logBonus(string $userRef, string $amount): void
{
    $sth = getConnect()->prepare("
        INSERT INTO
            `addons_pay_api` (
                `misc_id`,
                `category`,
                `user_ref`,
                `amount`,
                `creation_time`,
                `update_time`,
                `comment`
            )
        VALUES (
            '',
            :categoryId,
            :userRef,
            :amount,
            NOW(),
            NOW(),
            :payComment
        )");

    $sth->bindValue(':categoryId', CATEGORY_ID, PDO::PARAM_INT);
    $sth->bindValue(':payComment', PAY_COMMENT);
    $sth->bindParam(':userRef', $userRef);
    $sth->bindParam(':amount', $amount);

    $sth->execute();
}

try {
    /** Получение параметров клиента и бонуса */
    !is_null($client = getClient()) || exit;

    /** Проверка и добавление категории платежа */
    checkCategory() || addCategory();
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

try {
    /** Запись информации о бонусе в БД */
    logBonus($client['uid'], $client['amount']);
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}
