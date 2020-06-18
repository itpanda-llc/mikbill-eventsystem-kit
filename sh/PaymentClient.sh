#!/bin/bash

cd ../scripts/ || exit
#/usr/bin/php ./PaymentSMSNotice.php "$1" > /dev/null 2>&1
#/usr/bin/php ./CreditSMSNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./CreditSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentKomtetReceiptSend.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentBonusLog.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentLoyaltyBonusLog.php "$1" > /dev/null 2>&1
/usr/bin/php ./PaymentRouterOSSessionRemove.php "$1" > /dev/null 2>&1
/usr/bin/php ./CreditRouterOSSessionRemove.php "$1" > /dev/null 2>&1