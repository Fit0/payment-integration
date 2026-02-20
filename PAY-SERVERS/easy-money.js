// Simulador de servidor para imitar respuestas de los sistemas de pago ficticios
const express = require('express');
const app = express();
app.use(express.json());

// Endpoint para Pago Directo (Pago EasyMoney)
app.post('/process', (req, res) => {
    const { amount, currency } = req.body;

    let response = 'ok';
    // Si el amount es un número No entero, devuelve error
    if (Number.isInteger(amount) == false) {
        response = 'error';
    }

    if (response === 'ok') {
        res.status(200).send('ok'); // Devuelve ok
    } else {
        res.status(400).send('error'); // Devuelve error
    }
});

// Iniciar el servidor en el puerto 3000
const PORT = 3000;
app.listen(PORT, () => {
    console.log(`Servidor de pago EasyMoney ejecutándose en el puerto ${PORT}`);
});

