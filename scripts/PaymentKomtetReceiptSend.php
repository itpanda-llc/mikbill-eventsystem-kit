<?php

/**
 * Файл из репозитория MikBill-EventSystem-Kit
 * @link https://github.com/itpanda-llc/mikbill-eventsystem-kit
 */

declare(strict_types=1);

/**
 * ID магазина Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const KOMTET_MARKET_ID = '***';

/**
 * Секретный ключ магазина Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const KOMTET_MARKET_KEY = '***';

/**
 * ID очереди Комтет Касса
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const KOMTET_QUEUE_ID = '***';

/**
 * Псевдоним очереди Комтет Касса
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const KOMTET_QUEUE_NAME = KOMTET_QUEUE_ID;

/**
 * Путь к конфигурационному файлу MikBill
 * @link https://wiki.mikbill.pro/billing/config_file
 */
const CONFIG = '/var/www/mikbill/admin/app/etc/config.xml';

/** Наименование таблицы для ведения документов */
const RECEIPTS_TABLE_NAME = '__komtet_receipts_log';

/** Наименование колонки "Время создания" */
const CREATE_TIME_COLUMN_NAME = 'create_time';

/** Наименование колонки "Время обновления" */
const UPDATE_TIME_COLUMN_NAME = 'update_time';

/** Наименование колонки "ID пользователя" */
const USER_ID_COLUMN_NAME = 'user_id';

/** Наименование колонки "Контакт пользователя" */
const CONTACT_COLUMN_NAME = 'contact';

/** Наименование колонки "Внутренний номер" */
const INT_ID_COLUMN_NAME = 'int_id';

/** Наименование колонки "Внешний номер" */
const EXT_ID_COLUMN_NAME = 'ext_id';

/** Наименование колонки "Состояние задачи" */
const STATE_COLUMN_NAME = 'state';

/** Наименование колонки "Описание ошибки" */
const ERROR_COLUMN_NAME = 'error';

/** Наименование услуги */
const SERVICE_NAME = 'Домашний интернет';

/**
 * Место расчета
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const PAYMENT_ADDRESS = 'Офис';

/**
 * Адрес Callback-уведомлений
 * @link https://kassa.komtet.ru/integration/api
 * @link https://github.com/Komtet/komtet-kassa-php-sdk
 */
const CALLBACK_URL = '***';

require_once 'lib/func/getConfig.php';
require_once 'lib/func/getConnect.php';
require_once '../../../autoload.php';

use Komtet\KassaSdk;

/**
 * @return array|null Параметры клиента/документа
 */
function getReceipt(): ?array
{
    $sth = getConnect()->prepare("
        SELECT
            `users`.`user`,
            `users`.`uid`,
            IF(
                SUBSTRING(
                    `users`.`sms_tel`,
                    1,
                    1
                ) != '+',
                CONCAT(
                    '+',
                    `users`.`sms_tel`
                ),
                `users`.`sms_tel`
            ) AS
                `contact`,
            ROUND(
                `bugh_plategi_stat`.`summa`,
                2
            ) AS
                `amount`,
            IF(
                (
                    `stuff_personal`.`fio` = ''
                        OR
                    `stuff_personal`.`fio` IS NULL
                ),
                NULL,
                `stuff_personal`.`fio`
            ) AS
                `cashier_name`,
            IF(
                (
                    `stuff_personal`.`inn` = ''
                        OR
                    `stuff_personal`.`inn` IS NULL
                ),
                NULL,
                `stuff_personal`.`inn`
            ) AS
                `cashier_inn`
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
                        AND
                    `bugh_plategi_stat`.`summa` >= 0.01
        LEFT JOIN
            `addons_pay_api`
                ON
                    `addons_pay_api`.`user_ref` = `users`.`uid`
                        AND
                    `addons_pay_api`.`category` < 0
                        AND
                    `addons_pay_api`.`update_time` > DATE_SUB(
                        NOW(),
                        INTERVAL 10 SECOND
                    )
        LEFT JOIN
            `stuff_personal`
                ON
                    `stuff_personal`.`stuffid` = `bugh_plategi_stat`.`who`
        WHERE
            `users`.`uid` = :uId
                AND
            `users`.`sms_tel` IS NOT NULL
                AND
            `users`.`sms_tel` != ''
                AND
            `bugh_plategi_stat`.`uid` IS NOT NULL
                AND
            `addons_pay_api`.`user_ref` IS NULL
        ORDER BY
            `bugh_plategi_stat`.`plategid`
        DESC
        LIMIT
            1");

    $sth->bindParam(':uId', $_SERVER['argv'][1]);
    
    $sth->execute();

    $result = $sth->fetch(PDO::FETCH_ASSOC);

    return ($result !== false) ? $result : null;
}

function createReceiptTable(): void
{
    getConnect()->exec("
        CREATE TABLE IF NOT EXISTS
            `" . RECEIPTS_TABLE_NAME . "` (
                `id` INT AUTO_INCREMENT,
                `" . CREATE_TIME_COLUMN_NAME . "` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `" . UPDATE_TIME_COLUMN_NAME . "` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                `" . USER_ID_COLUMN_NAME . "` VARCHAR(128) NOT NULL,
                `" . CONTACT_COLUMN_NAME . "` VARCHAR(128) NOT NULL,
                `" . INT_ID_COLUMN_NAME . "` VARCHAR(128) NULL DEFAULT NULL,
                `" . EXT_ID_COLUMN_NAME . "` INT NULL DEFAULT NULL,
                `" . STATE_COLUMN_NAME . "` VARCHAR(128) NULL DEFAULT NULL,
                `" . ERROR_COLUMN_NAME . "` VARCHAR(128) NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            )
            ENGINE = InnoDB
            CHARSET=utf8
            COLLATE utf8_general_ci");
}

/**
 * @param string $userId ID пользователя
 * @param string $contact Номер телефона
 */
function logReceipt(string $userId, string $contact): void
{
    $sth = getConnect()->prepare("
        INSERT INTO
            `" . RECEIPTS_TABLE_NAME . "` (
                `" . USER_ID_COLUMN_NAME . "`,
                `" . CONTACT_COLUMN_NAME . "`
            )
        VALUES (
            :userId,
            :contact
        )");
    
    $sth->bindParam(':userId', $userId);
    $sth->bindParam(':contact', $contact);

    $sth->execute();
}

/**
 * @param string $userId ID пользователя
 */
function setReceiptId(string $userId): void
{
    $sth = getConnect()->prepare("
        UPDATE
            `" . RECEIPTS_TABLE_NAME . "`
        SET
            `" . INT_ID_COLUMN_NAME . "` = CONCAT(
                DATE_FORMAT(
                    NOW(),
                    '%y%m'
                ),
                `id`
            )
        WHERE
            `" . CREATE_TIME_COLUMN_NAME . "` > DATE_SUB(
                NOW(),
                INTERVAL 10 SECOND
            )
                AND
            `" . USER_ID_COLUMN_NAME . "`= :userId");

    $sth->bindParam(':userId', $userId);

    $sth->execute();
}

/**
 * @param string $userId ID пользователя
 * @return string Номер документа
 */
function getReceiptId(string $userId): string
{
    $sth = getConnect()->prepare("
        SELECT
            `" . INT_ID_COLUMN_NAME . "`
        FROM
            `" . RECEIPTS_TABLE_NAME . "`
        WHERE
            `" . CREATE_TIME_COLUMN_NAME . "` > DATE_SUB(
                NOW(),
                INTERVAL 10 SECOND
            )
                AND
            `" . USER_ID_COLUMN_NAME . "` = :userId
                AND
            `" . EXT_ID_COLUMN_NAME . "` IS NULL
        ORDER BY
            `id`
        DESC
        LIMIT
            1");

    $sth->bindParam(':userId', $userId);

    $sth->execute();

    return $sth->fetch(PDO::FETCH_COLUMN);
}

/**
 * @param string $account Аккаунт
 * @return string Наименование позиции
 */
function getPositionName(string $account): string
{
    return sprintf("%s (Л/СЧ N%s)", SERVICE_NAME, $account);
}

/**
 * @param string $intId Номер документа
 * @param int $extId Номер документа Комтет Касса
 * @param string $state Состояние задачи
 */
function updateReceipt(string $intId,
                       int $extId,
                       string $state): void
{
    $sth = getConnect()->prepare("
        UPDATE
            `" . RECEIPTS_TABLE_NAME . "`
        SET
            `" . EXT_ID_COLUMN_NAME . "` = :extId,
            `" . STATE_COLUMN_NAME . "` = :state
        WHERE
            `" . INT_ID_COLUMN_NAME . "` = :intId");

    $sth->bindParam(':intId', $intId);
    $sth->bindParam(':extId', $extId, PDO::PARAM_INT);
    $sth->bindParam(':state', $state);

    $sth->execute();
}

try {
    !is_null($receipt = getReceipt()) || exit;
    createReceiptTable();

    getConnect()->beginTransaction() || exit(
        "Begin a transaction failed\n");
} catch (PDOException $e) {
    exit(sprintf("%s\n", $e->getMessage()));
}

try {
    logReceipt($receipt['uid'], $receipt['contact']);
    setReceiptId($receipt['uid']);

    $receiptId = getReceiptId($receipt['uid']);
} catch (PDOException $e) {
    try {
        getConnect()->rollBack() || exit(
            "Rollback a transaction failed\n");
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}

$position = new KassaSdk\Position(getPositionName($receipt['user']),
    (float) $receipt['amount'],
    1,
    (float) $receipt['amount'],
    new KassaSdk\Vat(Komtet\KassaSdk\Vat::RATE_NO));

$position->setCalculationMethod(KassaSdk\CalculationMethod::FULL_PAYMENT)
    ->setCalculationSubject(KassaSdk\CalculationSubject::SERVICE);

$check = KassaSdk\Check::createSell($receiptId,
    $receipt['contact'],
    KassaSdk\TaxSystem::SIMPLIFIED_IN,
    PAYMENT_ADDRESS);

$check->addPosition($position)
    ->addPayment(new KassaSdk\Payment(KassaSdk\Payment::TYPE_CARD,
        (float) $receipt['amount']))
    ->setCallbackUrl(CALLBACK_URL);

if (!is_null($receipt['cashier_name']))
    $check->addCashier(new KassaSdk\Cashier($receipt['cashier_name'],
        $receipt['cashier_inn']));

$queueManager = new KassaSdk\QueueManager(
    new KassaSdk\Client(KOMTET_MARKET_ID, KOMTET_MARKET_KEY));

$queueManager->registerQueue(KOMTET_QUEUE_NAME, KOMTET_QUEUE_ID);

try {
    $status = $queueManager->putCheck($check, KOMTET_QUEUE_NAME);

    updateReceipt($receiptId, $status['id'], $status['state']);
    getConnect()->commit() || exit(
        "Commit a transaction failed\n");
} catch (
    InvalidArgumentException
    | KassaSdk\Exception\ApiValidationException
    | KassaSdk\Exception\ClientException
    | PDOException $e
) {
    try {
        getConnect()->rollBack() || exit(
            "Rollback a transaction failed\n");
    } catch (PDOException $e) {
        exit(sprintf("%s\n", $e->getMessage()));
    }

    exit(sprintf("%s\n", $e->getMessage()));
}
