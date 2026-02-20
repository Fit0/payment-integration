// Simulador de servidor para imitar respuestas de los sistemas de pago ficticios
const express = require('express');
const app = express();
app.use(express.json());

// Endpoint para iniciar Super Walletz
app.post('/pay', (req, res) => {
    const { amount, currency, description, callback_url } = req.body;

    // Simula una respuesta inicial exitosa
    res.status(200).send({ transaction_id: 'trx_' + Math.floor(Math.random() * 100000) });

    // Simula el envío del webhook tras un tiempo de 5 segundos
    setTimeout(() => {
        const webhookResponse = {
            transaction_id: 'trx_' + Math.floor(Math.random() * 100000),
            status: 'success'
        };

        // Realiza 3 llamadas POST consecutivas al callback_url con la respuesta del webhook

        let send = () => {
            const axios = require('axios');
            axios.post(callback_url, webhookResponse)
                .then(() => console.log('Webhook enviado: ', webhookResponse))
                .catch((error) => console.error('Error enviando webhook: ', error.message));
        }

        for (let i = 0; i < 3; i++) {
            setTimeout(send, 1000);
        }

    }, 5000); // 5 segundos de retraso para simular el tiempo de procesamiento
});

// Iniciar el servidor en el puerto 3003
const PORT = 3003;
app.listen(PORT, () => {
    console.log(`Servidor de pago SuperWalletz ejecutándose en el puerto ${PORT}`);
});
