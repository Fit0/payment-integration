**Prueba Técnica Laravel: Integración de Sistemas de Pago**

### Descripción General:

En esta prueba deberás implementar la integración de dos sistemas de pago ficticios utilizando Laravel. Los dos sistemas de pago serán:

1. **Pago EasyMoney**
2. **Pago SuperWalletz**

### Requisitos

#### 1. Pago EasyMoney

- Deberás integrar el sistema de pago ficticio EasyMoney con Laravel. Esta es la documentación de la API de EasyMoney:
  
- Debes hacer una llamada POST a la siguiente URL:
  - URL: `/process`
  - Request Body:
    ```json
    {
      "amount": "<monto>",
      "currency": "<moneda>"
    }
    ```
- Lamentablemente el sistema de pago EasyMoney no puede procesar datos decimales, en ese caso nos devolverá un error que debemos manejar. Igualmente, maneja todos los casos de error que puedan ocurrir.

#### 2. Pago SuperWalletz

- Deberás integrar el sistema de pago ficticio SuperWalletz con Laravel. Esta es la documentación de la API de SuperWalletz:

- Debes hacer una llamada POST a la siguiente URL:
  - URL: `/pay`
  - Request Body:
    ```json
    {
      "amount": "<monto>",
      "currency": "<moneda>",
      "callback_url": "<tu_url_para_confirmacion>"
    }
    ```

- Al hacer la llamada, este nos devolverá un mensaje "success" con el id de la transacción en la plataforma de pago.
- Pasados unos segundos, la plataforma de pago nos mandará un webhook con la confirmación de pago a la URL que especificaste en el request body.

### Consideraciones Adicionales

- Se deben guardar todas las transacciones y su estado en la base de datos, independientemente de si fueron exitosas o no.
- También debemos guardar todas las requests/peticiones realizadas a la plataforma de pago, y también los webhooks que recibimos, para su posterior análisis por parte del equipo de BI.
- En la carpeta `/PAY-SERVERS` se encuentra un archivo `easy-money.js` y un archivo `super-walletz.js` que son simuladores de los servidores de pago. No debes modificarlos. Para que funcionen correctamente, debes ejecutar los siguientes comandos:

```bash
npm install
node easy-money.js # Ejecuta el servidor de Pago EasyMoney
node super-walletz.js # Ejecuta el servidor de Pago SuperWalletz
```

¡Buena suerte!

