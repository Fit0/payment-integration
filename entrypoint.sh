composer install

if [ ! -f .git/hooks/pre-commit ]
then
  cp .env.example .env
fi

cd PAY-SERVERS
npm install
node easy-money.js & # Ejecuta el servidor de Pago EasyMoney
node super-walletz.js & # Ejecuta el servidor de Pago SuperWalletz

tail -f /dev/null
