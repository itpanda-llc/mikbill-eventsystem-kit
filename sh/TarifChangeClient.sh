#!/bin/bash

cd ../scripts/ || exit
#/usr/bin/php ./TarifChangeClientSMSNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./TarifChangeClientSocNotice.php "$1" > /dev/null 2>&1
/usr/bin/php ./TarifChangeDiscountRemove.php "$1" > /dev/null 2>&1
/usr/bin/php ./TarifChangeRouterOSSessionRemove.php "$1" > /dev/null 2>&1