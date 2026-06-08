1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [Guias](http://developers.paguelofacil.com/guias "Guias")
3.  Enlace de Pago

# Enlace de Pago

Este proceso de pago también se conoce como pago de Offsite o botón de pago. Para realizar el procesamiento el cliente abandona su sitio web para procesar el pago y se redirige al sitio seguro de Payfacil.

## Prerrequisitos

CCLW

Llaves de conexión al API

Certificado SSL

TLS 1.3 o superior

## Base URL de ambientes

Producción

https://secure.paguelofacil.com/  
https://api.pfserver.net/

Pruebas

https://sandbox.paguelofacil.com/  
https://api-sand.pfserver.net/

**IMPORTANTE —** PagueloFacil tiene credenciales para [ambiente de pruebas](https://demo.paguelofacil.com) y credenciales para [ambiente de producción](https://comercios.paguelofacil.com) que permiten integrar los métodos de pago, no se deben confundir al momento de realizar las configuraciones, cada ambiente requiere de sus credenciales. El monto mímo es de $ 1.00 y la moneda permitida es USD.

## Botones

**En español**

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)`https://assets.paguelofacil.com/images/btn-svg/btn_es.svg`

**En ingles**

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)`https://assets.paguelofacil.com/images/btn-svg/btn_en.svg`

## Obtener URL de redirección (Método seguro)

Copia, pega en tu web el ejemplo de php. Modifica los parámetros `CCLW`, `CMTN` y `CDSC`

Ejemplo, `php` del código de petición de pago por método POST.

*   [PHP](#php)

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)

`$data = array( "CCLW" => $cclw , "CMTN" => $amount, "CDSC" => $descrition, "RETURN_URL" => '68747470733A2F2F70616775656C6F666163696C73612E7A656E6465736B2E636F6D2F6167656E742F66696C746572732F3439313933393538', "PF_CF" => '5B7B226964223A227472616D6974654964222C226E616D654F724C6162656C223A2249642064656C205472616D697465222C2276616C7565223A2254494432333435227D5D', "PARM_1" => '19816201', "EXPIRES_IN" => 3600, ); $postR=""; foreach($data as $mk=>$mv) { $postR .= "&".$mk."=".$mv; } $ch = curl_init(); curl_setopt($ch,CURLOPT_URL, "https://secure.paguelofacil.com/LinkDeamon.cfm");  //curl_setopt($ch,CURLOPT_URL, "https://secure.paguelofacil.com/LinkDeamon.cfm/AUTH");   ****En Caso de querer Pre-autorizar  y capturar en procesos separados. curl_setopt($ch, CURLOPT_POST, true); curl_setopt( $ch, CURLOPT_AUTOREFERER, true ); curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); curl_setopt($ch,CURLOPT_RETURNTRANSFER,true); curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded','Accept: */*')); curl_setopt($ch,CURLOPT_POSTFIELDS,$postR); $result = curl_exec($ch);`

## URL para re-dirección

Toma el Valor `url` , de la respuesta del servicio y realiza la redirección. De esta forma ninguno de tis datos confidenciales como `CMTN` y `CCLW` son expuestos a terceros.

```

{
"headerStatus":{
"code":200,"description":"Success"},
"serverTime":"2021-01-12T16:13:50","message":"Success",
"data":{"url":"https://checkout.paguelofacil.com?code=LK-RQC5CO5G3TLNNJUU","code":"LK-RQC5CO5G3TLNNJUU"},
"success":true
}
```

**IMPORTANTE —** Debes generar un nuevo enlace para cada transacción que deseas realizar. Las url son de un solo uso.

###### Descripción de los parámetros

| Parámetro   | Tipo                                            | Requerido | Descripción                                                                                                                                         |
| ----------- | ----------------------------------------------- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| CCLW        | String  <br>Ej.: A7BFCAF7B6……….                 | Si        | Este es el código web suministrado por Payfacil y que identifica a su comercio                                                                      |
| CMTN        | Numeric, Money  <br>Ej.: 10.00 – 1450.15 – 9.14 | Si        | Monto de la compra                                                                                                                                  |
| CTAX        | Numeric, Money  <br>Ej.: 7.00 – 70.00 – 0.14    | No        | Monto correspondiente al ITBMS dentro del monto de la transacción                                                                                   |
| CDSC        | String  <br>MaxLength:150                       | Si        | Descripción de la compra                                                                                                                            |
| RETURN\_URL | String URL codificado en Hexadecimal            | No        | URL de retorno donde desea recibir la respuesta                                                                                                     |
| PF\_CF      | String JSON codificado en Hexadecimal           | No        | String en formato JSON con la siguiente estructura `[ {"id":"3Z4YNBQ","nameOrLabel":"id","type":"hidden","value":"3Z4YNBQ"}]`                       |
| CARD\_TYPE  | String separado por comas                       | No        | Sirve para determinar que métodos de pago, queremos mostrar en nuestro enlace de pago. Valores: NEQUI,CASH,CLAVE, CARD, CRYPTO \*\*\*Opcional\*\*\* |
| PARM\_1     | String  <br>MaxLength:150                       | No        | \*\*\*Parámetro personalizado\*\*\*, es posible enviar mas de 1 y puedes nombrarlo como desees, todos son retornados en la respuesta.               |
| EXPIRES\_IN | integer  <br>Ej.: 3600 – 600 – 60               | No        | Cantidad de segundos máxima que desea recibir el pago                                                                                               |

## Respuesta

PagueloFacil automáticamente genera una vista de respuesta al usuario final, pero si lo desea se puede retornar la transacción a su página web.  Entonces, debes enviar el parámetro RETURN\_URL con una URL válida códificada como Hexadecimal y su sitio será el encargado de proporcionarle una respuesta al usuario final sobre el estado de su transacción.

###### Transacción Aprobada

![Transacción Aprobada](/src/Frontend/Files/MediaLibrary/08/aprobada.png)

###### Transacción Denegada

![Transacción Denegada](/src/Frontend/Files/MediaLibrary/09/noaprobada.png)

###### Descripción de los parámetros

| Parámetro   | Descripción                                                                                                                     |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------- |
| TotalPagado | 0 si denegada, el monto cobrado si es aceptada                                                                                  |
| Fecha       | Fecha de la transacción en formato dd/mm/yyyy                                                                                   |
| Hora        | Hora de la transacción en formato HH:MM.SS                                                                                      |
| Tipo        | Tipo de tarjeta VISA , MC para MasterCard, Wallet para transacciones recibidas a través de PagueloFacil App                     |
| Oper        | Numero de Operación alfanumérico                                                                                                |
| Usuario     | Nombre y Apellidos del tarjeta habiente                                                                                         |
| Email       | Email del tarjetahabiente                                                                                                       |
| Estado      | Aprobada o Denegado                                                                                                             |
| Razon       | Por que fue Denegada la transacción por el banco emisor de la tarjeta de crédito. Solo se envía cuando la respuesta es denegada |
| PARM\_1     | valores enviados en la solicitud, son retornados tal como fueron enviados.                                                      |

###### Ejemplo de parámetros de Retorno 

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`//Pagos a través de anónimo tipo VISA {"TotalPagado":"3.21","Fecha":"27\/07\/2021","Hora":"08:59:45","Tipo":"VISA","Oper":"SANDBOX_LK-SKADZYRMUMB4","Usuario":"prueba pf","Email":"correo@prueba.com","Estado":"Aprobada","Razon":"VER UNAVAILBLE","CMTN":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.719","CCLW":"1B09E4FCE502FE86540D14AC1031BDF54B399D919A23EAC43144CB540A8466642835566D3A77D3641CABF564BFA46608F5915083E110AE3C91F231459F05C27C"}  //Pagos a través de anónimo tipo PagoCash {"TotalPagado":"0","Fecha":"27\/07\/2021","Hora":"09:03:58","Tipo":"CASH","Oper":"SANDBOX_PP48123808","Usuario":"null","Email":"correo@prueba.com","Estado":"Pendiente","Razon":"PENDING PAYMENT","CMTN":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.720","CCLW":"1B09E4FCE502FE86540D14AC1031BDF54B399D919A23EAC43144CB540A8466642835566D3A77D3641CABF564BFA46608F5915083E110AE3C91F231459F05C27C"}  //Pagos a través de anónimo tipo CLAVE {"TotalPagado":"3.21","Fecha":"16\/08\/2021","Hora":"16:08:28","Tipo":"CLAVE","Oper":"LK-PGRPP1BNEYDW","Usuario":"null","Email":"correo@prueba.com","Estado":"Aprobada","Razon":"Operaci\u00f3n Satisfactoria","CMTN":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.774","CCLW":"1B09E4FCE502FE86540D14AC1031BDF54B399D919A23EAC43144CB540A8466642835566D3A77D3641CABF564BFA46608F5915083E110AE3C91F231459F05C27C","RelatedTx":"LK-JHRWYP6E8WXZ"}  //Pagos a través de anónimo tipo NEQUI {"TotalPagado":"1.07","Fecha":"16\/08\/2021","Hora":"16:41:10","Tipo":"NEQUI","Oper":"LK-GPDVOKF052OU","Usuario":"null","Email":"correo@prueba.com","Estado":"Aprobada","Razon":"PAGADO","CMTN":"1.07","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.777","CCLW":"2C7C0A86517030E7A1C96166DE612C064567D04A6C6CFEF109FDB85BAB0E4CC622EBEF2824785778A1F5179AE1FA7B1999FB9ACF71E00FEBFCA6AA8083BCEFD6"}   //Pagos a través de PagueloFacil App (usuarios autenticados) tipo Mastercard {"TotalPagado":"3.21","Fecha":"27\/07\/2021","Hora":"09:09:10","Tipo":"MC","Oper":"SANDBOX_PFW-Z6BCK2TCR2R","Usuario":"prueba pf","Email":"wajih@paguelofacil.com","Estado":"Aprobada","Razon":"VER UNAVAILBLE","CMTN":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.721","CCLW":"1B09E4FCE502FE86540D14AC1031BDF54B399D919A23EAC43144CB540A8466642835566D3A77D3641CABF564BFA46608F5915083E110AE3C91F231459F05C27C","activityCode":"0000000280","txAmount":"3.21","Order":"721"}  //Pagos a través de PagueloFacil App (usuarios autenticados) tipo PagoCash {"TotalPay":"0","Date":"2021-07-27T09:16:50","Type":"CASH","Oper":"SANDBOX_PP98057873","User":"null","Email":"wajih@paguelofacil.com","Status":"Pending","StatusCode":"PUNTOPAGO","msg":"PENDING PAYMENT","RequestPay":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.722"}  //Pagos a través de PagueloFacil App (usuarios autenticados) tipo VISA {"TotalPagado":"15.15","Fecha":"24\/07\/2021","Hora":"09:58:03","Tipo":"VISA","Oper":"SANDBOX_PFW-EP10GCGQKDI","Usuario":"Yousset Chacon","Email":"cpag3@prueba.com","Estado":"Aprobada","Razon":"VER UNAVAILBLE","CMTN":"15.15","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.659","CCLW":"15224BE0CBB8EAAC33B53850FF71EAE732253AFC4AB224938A491D0B3E9D3F7B","activityCode":"0000000276","txAmount":"15.15","Order":"659"}  //Pagos a través de PagueloFacil App (usuarios autenticados) tipo CLAVE {"TotalPay":"3.21","Date":"2021-08-16T16:11:36","Type":"CLAVE","Oper":"LK-EADXDQM6KILB","User":"prueba pf pf","Email":"wajih@paguelofacil.com","Status":"Approved","StatusCode":"Procesada","msg":"Operaci\u00f3n Satisfactoria","RequestPay":"3.21","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.775","RelatedTx":"LK-TWXPATXBCDXC"}  //Pagos a través de PagueloFacil App (usuarios autenticados) tipo NEQUI {"TotalPay":"1.07","Date":"2021-08-16T16:45:07","Type":"NEQUI","Oper":"LK-ZFD3SF7FUMZD","User":"null","Email":"maryiliana@gmail.com","Status":"Approved","StatusCode":"35","msg":"PAGADO","RequestPay":"1.07","CDSC":"https:\/\/woocommerce.pfserver.net Orden Nro.778"}   //Excepciones {"headerStatus":{"code":615,"description":"INVALID SERVICE GATEWAY OR DATA GATEWAY"},"serverTime":"2021-08-11T21:56:20","message":null,"requestId":null,"data":{},"success":false}`
 

###### Ejemplo de validación de Retorno

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`//******** GET VARS *********/   ////linkdemon/////   $response = $_REQUEST;   //Puede enviar los parámetros que desee en el enlace y le serán devueltas   if ($TotalPagado>0 && $Estado != 'Denegada') {    //Podemos hacer validaciones adicionales de nuestro sistema   echo "Pago Completado";   } else {    echo "Su pago ha presentado problema:";    echo 'Estado: '.$Estado;  eecho 'Razón: '.$Razon;     }` 
 

## Configuración de webhook

Es posible configurar un URL para recibir Vía POST el detalle de cada transacción, esto te permite obtener actualizaciones en tiempo real, automatizando procesos como la reconciliación de pagos y la validación de transacciones. Esto mejora la eficiencia operativa, reduce el margen de error y proporciona un control más preciso sobre las operaciones. Además, facilita la detección de anomalías para fortalecer la seguridad y permite integrar fácilmente los datos con otros sistemas, escalando sin problemas a medida que aumentan las transacciones.

###### Parámetros enviado a través del webhook

| Nombre             | Tipo     | Formato               | Descripción                                                                                                                                                                                                                                                                                      |
| ------------------ | -------- | --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| date               | DateTime | yyyy-MM-dd'T'HH:mm:ss | Fecha y Hora de la transacción                                                                                                                                                                                                                                                                   |
| relatedTx          | String   | STG-7TYUHNJMKLHUAM    | Código de Operación de la transacción relacionada, como una autorización 3DS                                                                                                                                                                                                                     |
| description        | String   |                       | Descripción enviada por el comercio al momento de solicitar la transacción.                                                                                                                                                                                                                      |
| merchantDescriptor | String   |                       | El softdescriptor puede modificar y/o adicionar información que ve el tarjeta habiente en sus notificaciones y/o estado de cuenta de su tarjeta.                                                                                                                                                 |
| type               | String   | VISA / MC             | Método de Pago                                                                                                                                                                                                                                                                                   |
| cardToken          | String   |                       | CardToken interno, para uso interno                                                                                                                                                                                                                                                              |
| userLogn           | String   |                       | username del usuario en PagueloFacil que se utilizó para generar la transacción.                                                                                                                                                                                                                 |
| idUsr              | String   |                       | Identificador interno del usuario en PagueloFacil que se utilizó para generar la transacción.                                                                                                                                                                                                    |
| revisionLevel      | String   |                       | En caso de que la transacción requiera una revisión por parte del comercio indicará el nivel.                                                                                                                                                                                                    |
| totalPay           | String   | Ej. 1356.25           | Monto total de la transacción                                                                                                                                                                                                                                                                    |
| binInfo            | JSON     |                       | Información de fraude de la tarjeta (El retorno de estos datos puede variar según el proveedor). Contiene datos como: País y Banco emisor de la tarjeta, score de Riesgo de la transacción, de la IP del tarjeta habiente valorado comúnmente en 0 (Menor riesgo)  y 99.99 (Mayor Riesgo).       |
| displayNum         | String   |                       | Últimos digitos de la tarjeta                                                                                                                                                                                                                                                                    |
| returnUrl          | String   |                       | Url para mostrar un recibo de pago del detalle de la transacción con los datos de la transacción.                                                                                                                                                                                                |
| requestPayAmount   | Numeric  |                       | Monto solicitado en la petición de la petición                                                                                                                                                                                                                                                   |
| email              | String   |                       | Correo del cliente                                                                                                                                                                                                                                                                               |
| isExternalUrl      | Boolean  |                       | De uso interno en PagueloFacil                                                                                                                                                                                                                                                                   |
| authStatus         | String   |                       | Código ISO de aprobación o denegación proveído por la marca (VISA/Mastercard). Para mayor información visite: [Mastercard](https://developer.mastercard.com/mastercard-merchant-presented-qr/documentation/server-apis/response-error-codes/#network-response-codes)                             |
| cardType           | String   | VISA / MC             | Proveedor de la tarjeta usada para la transacción                                                                                                                                                                                                                                                |
| userName           | String   |                       | Nombre del tarjetahabiente                                                                                                                                                                                                                                                                       |
| idtx               | String   |                       | Identificador interno.                                                                                                                                                                                                                                                                           |
| inRevision         | Boolean  |                       | Indica si la transacción debe ser revisada por el dueño de la cuenta.                                                                                                                                                                                                                            |
| isTxCampaign       | Boolean  |                       | Si es una transacciñon generada a través de una campaña                                                                                                                                                                                                                                          |
| name               | String   |                       | Nombre del tarjetahabiente                                                                                                                                                                                                                                                                       |
| operationType      | String   |                       | Tipo de operación. Los valores recibidos son:<br><br>*   AUTH (Pre-Autorización),<br>*   CAPTURE (Captura),<br>*   AUTH\_CAPTURE (Sale, Autorización y Captura),<br>*   3DS (Validación de Fraude)<br>*   RECURRENT<br>*   REVERSE<br>*   REVERSE\_CAPTURE                                       |
| txDescriptor       | String   |                       | El softdescriptor puede modificar y/o adicionar información que ve el tarjeta habiente en sus notificaciones y/o estado de cuenta de su tarjeta.                                                                                                                                                 |
| revisionOptions    | String   |                       | Opciones de revisión.                                                                                                                                                                                                                                                                            |
| codOper            | String   |                       | Código de operación de la transacción, es la referencia que debe usarse para consultar y/o operar con los servicios de PagueloFacil, para reversar, devolver y/o volver a procesar una transacción.                                                                                              |
| status             | Integer  |                       | Indica si la transacción fue aprobada (1) o declinada (0). Mas información visite: [Estados de las transacciones](https://soporte.paguelofacil.com/hc/es-419/articles/7747616029203-API-Estados-de-las-transacciones)                                                                            |
| messageSys         | String   |                       | Indica el mensaje del sistema. Mensaje de autorización de las marcas Visa y MasterCard. Ver Unavailable aparece porque la dirección que envía es válida AVS (Address Verification Service) que indica si la dirección es la asociada a la tarjeta  correspondientes al código ISO de authStatus. |

###### Ejemplo de parámetros del webhook 

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`{   "date": "2025-07-17T11:05:14",   "relatedTx": "STG-7TYUHNJMKLHUAM",   "description": " Yo soy Alam Brito de Cobre el mejor conductor de Uber",   "merchantDescriptor": "PF*",   "type": "MC",   "cardToken": "1690163708596321459",   "userLogn": "Brito Alam",   "idUsr": 64,   "revisionLevel": null,   "totalPay": "38.39",   "binInfo": {     "id": "69c57914-890c-4413-a485-d3d171cb73ed",     "risk_score": 55.0,     "funds_remaining": 397.592,     "queries_remaining": 19879,     "ip_address": {       "risk": 22.5,       "city": {         "confidence": 10,         "geoname_id": 4744870,         "names": {           "de": "Ashburn",           "en": "Ashburn",           "es": "Ashburn",           "fr": "Ashburn",           "ja": "",           "pt-BR": "Ashburn",           "ru": "",           "zh-CN": ""         }       },       "continent": {         "code": "NA",         "geoname_id": 6255149,         "names": {           "de": "Nordamerika",           "en": "North America",           "es": "Norteamrica",           "fr": "Amrique du Nord",           "ja": "",           "pt-BR": "Amrica do Norte",           "ru": " ",           "zh-CN": ""         }       },       "country": {         "confidence": 99,         "geoname_id": 6252001,         "is_high_risk": false,         "iso_code": "US",         "names": {           "de": "USA",           "en": "United States",           "es": "Estados Unidos",           "fr": "tats Unis",           "ja": "",           "pt-BR": "EUA",           "ru": "",           "zh-CN": ""         }       },       "location": {         "accuracy_radius": 1000,         "average_income": 33772,         "latitude": 39.0469,         "local_time": "2025-07-17T12:05:14-04:00",         "longitude": -77.4903,         "metro_code": 511,         "population_density": 1443,         "time_zone": "America/New_York"       },       "postal": { "code": "20149", "confidence": 10 },       "registered_country": {         "geoname_id": 6252001,         "iso_code": "US",         "names": {           "de": "USA",           "en": "United States",           "es": "Estados Unidos",           "fr": "tats Unis",           "ja": "",           "pt-BR": "EUA",           "ru": "",           "zh-CN": ""         }       },       "subdivisions": [         {           "confidence": 40,           "geoname_id": 6254928,           "iso_code": "VA",           "names": {             "de": "Virginia",             "en": "Virginia",             "es": "Virginia",             "fr": "Virginie",             "ja": "",             "pt-BR": "Virgnia",             "ru": "",             "zh-CN": ""           }         }       ],       "traits": {         "autonomous_system_number": 14618,         "autonomous_system_organization": "AMAZON-AES",         "domain": "amazonaws.com",         "ip_address": "34.231.38.183",         "isp": "Amazon.com",         "organization": "Amazon.com",         "user_type": "hosting"       }     },     "credit_card": {       "issuer": { "name": "BANCO DEL TESORO, C.A., BANCO UNIVERSAL" },       "brand": "Mastercard",       "country": "VE",       "is_prepaid": false,       "type": "debit"     },     "email": {       "first_seen": "2024-02-29",       "is_free": false,       "is_high_risk": false     },     "disposition": { "action": "manual_review", "reason": "custom_rule" }   },   "displayNum": "21453",   "returnUrl": "https://checkout.paguelofacil.com/pf/default-receipt/AUTH_CAP-IMUXPG",   "requestPayAmount": 38.39,   "email": "alambrito@decobre.com",   "isExternalUrl": false,   "authStatus": "00",   "cardType": "MC",   "userName": "Alam Brito",   "idtx": 123456,   "inRevision": false,   "isTxCampaign": false,   "name": "Alam Brito",   "operationType": "AUTH_CAPTURE",   "txDescriptor": "PF*",   "revisionOptions": null,   "codOper": "AUTH_CAP-IMUXPG",   "status": 1,   "messageSys": "Aprobada" }`
 

## Tarjetas de Pruebas

Con estos números de Tarjetas, podrás realizar transacciones aprobadas en cualquiera de nuestros servicios. En cuanto a las fechas de vencimiento te funcionan cualquier mes y año mayor o igual a la fecha actual y para el código de seguridad (CVV2, CVC2) cualquiera tres digitos númericos.

![](/src/Frontend/Files/MediaLibrary/00/visa-logo_1.png)

4059310181757001  
4916012776136988  
4716040174085053  
4143766247546688  
4929019201087046

![](/src/Frontend/Files/MediaLibrary/02/mastercard-vrt-rev-92px-2x.png)

5517747952039692  
5451819737278230  
5161216979741515  
5372362326060103  
5527316088871226

![](/src/Frontend/Files/MediaLibrary/12/clave.png)

**6394240621480747**  
CVV: **570**  
Fecha: **04-24**  
PIN: **0482**  
Transacción Aprobada

**5890846081457820**  
CVV: **867**  
Fecha: **07-20**  
PIN: **2944**  
Transacción Aprobada

**5890840000000027**  
CVV: **723**  
Fecha: **04-21**  
PIN: **9999**  
Transacción Aprobada

![](/src/Frontend/Files/MediaLibrary/12/clave.png)

**5038460000000035**  
CVV: **490**  
Fecha: **04-21**  
PIN: **1234**  
Tarjeta expirada


---


1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  Diccionario de Datos

## Condicionales

| Nombre                   | Requerido | Tipo   | Formato                                |
| ------------------------ | --------- | ------ | -------------------------------------- |
| EQUAL                    | No        | String | {field}$eq{value}                      |
| IS\_NULL                 | No        | String | {field}$null                           |
| IS\_NOT\_NULL            | No        | String | {field}$nnull                          |
| IS\_TRUE                 | No        | String | {field}$true                           |
| IS\_FALSE                | No        | String | {field}$false                          |
| IS\_EMPTY                | No        | String | {field}$empty                          |
| IS\_NOT\_EMPTY           | No        | String | {field}$IS\_NOT\_EMPTY                 |
| LIKE                     | No        | String | {field}$lk{value}                      |
| NOT\_LIKE                | No        | String | {field}$nlk{value}                     |
| BETWEEN                  | No        | String | {field}$bt{value}::{value2}            |
| LESS\_THAN               | No        | String | {field}$lt{value}                      |
| LESS\_THAN\_OR\_EQUAL    | No        | String | {field}$le{value}                      |
| GREATER\_THAN            | No        | String | {field}$gt{value}                      |
| GREATER\_THAN\_OR\_EQUAL | No        | String | {field}$ge{value}                      |
| IN                       | No        | String | {field}$in{value}::{value2}::{valueN}  |
| NOT\_IN                  | No        | String | {field}$nin{value}::{value2}::{valueN} |
| NOT\_EQUAL               | No        | String | {field}$ne{value}                      |

## Operadores

| Name            | Required | Type   | Format                   |
| --------------- | -------- | ------ | ------------------------ |
| COUNT           | No       | String | {field}::COUNT           |
| SUM             | No       | String | {field}::SUM             |
| MAX             | No       | String | {field}::MAX             |
| MIN             | No       | String | {field}::MIN             |
| AVG             | No       | String | {field}::AVG             |
| DISTINCT        | No       | String | {field}::DISTINCT        |
| COUNT\_DISTINCT | No       | String | {field}::COUNT\_DISTINCT |

## Response Code

| Code | Descripción                                                                            |
| ---- | -------------------------------------------------------------------------------------- |
| 100  | User / Password Invalid                                                                |
| 200  | Success                                                                                |
| 300  | There was an error                                                                     |
| 310  | There was an error, processing credit card                                             |
| 400  | Invalid request                                                                        |
| 410  | Invalid Api Key                                                                        |
| 420  | Invalid Api Version                                                                    |
| 430  | Invalid KWP                                                                            |
| 500  | Invalid Session                                                                        |
| 510  | Session Expired                                                                        |
| 520  | User not started session                                                               |
| 530  | Haven't privileges to access the service                                               |
| 540  | The user is already logged in the application, please close the session and try again. |
| 550  | Error saving data, in the database                                                     |
| 551  | Error, Invalid Field                                                                   |
| 560  | You do not have privileges to access this service or funtionality.                     |
| 900  | URL TimeOut Connection                                                                 |
| 910  | Please check, your connection internet.                                                |
| 920  | There was an Exception.                                                                |

---

1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  Respuesta

## Payload

| Nombre           | Tipo                        | Formato               | Requerido | Descripción                                    |
| ---------------- | --------------------------- | --------------------- | --------- | ---------------------------------------------- |
| **headerStatus** | Json                        |                       | Si        | Header Status, Indica el estado de la consulta |
| code             | Integer                     |                       | Si        | Código del estado de la respuesta              |
| description      | String                      |                       | Si        | Descripción del estado de la respuesta         |
| serverTime       | Date as String              | yyyy-MM-dd'T'HH:mm:ss | Si        | Current Server Time                            |
| message          | String                      |                       | No        | Mensaje de la respuesta                        |
| success          | Boolean                     |                       | No        | Retorna true si el headerStatus es SUCCESS     |
| data             | String \| Json \| JsonArray |                       | No        | Contenido de la repuesta del servicio          |


---

1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  Solicitud

## Headers

| Parámetro     | Tipo   | Formato                                                                                          | Requerido | Descripción                                                                                                                                        |
| ------------- | ------ | ------------------------------------------------------------------------------------------------ | --------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authorization | String | [{your accessToken}](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) | Si        | Autenticación para acceso al servicio  [¿Cómo obtengo mi accessToken?](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) |

## Parámetros

| Parámetro   | Tipo    | Formato                                         | Requerido | Descripción                                                                                  |
| ----------- | ------- | ----------------------------------------------- | --------- | -------------------------------------------------------------------------------------------- |
| Filter      | String  | {field}::{value}\|{field2}::{value} Conditional | No        | Permite realizar filtros y consultas sobre el servicio. Los Query Param son no casesensitive |
| Conditional | String  |                                                 | No        | {field}{operator}{value}\|{field2}{operator $bt}{value}::{value2}                            |
| Limit       | Integer |                                                 | No        | Indica el numero maximo de resultados esperados                                              |
| Offset      | Integer |                                                 | No        | Indica desde donde se retornara la consulta, esto es usado para paginacion                   |
| Sort        | String  | {field},{-field2}                               | No        | Permite ordenar la consulta, si quiere hacer un order descendente use - antes del campo      |
| Field       | String  | {field},{field2::{Operator}}                    | No        | Permite retornar solo los campos indicados, y aplicar operaciones sobre estos                |

---

1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  Transacciones

# Consultar Transacciones

A través de nuestas API's puedes consultar las transacciones por código de Operación "codOper"  y por rango de fechas. En esta documentación te damos un ejemplo de como consumir el servicio.

## Prerrequisitos

ACCESS TOKEN

Token de acceso del usuario

Certificado SSL

TLS 1.3 o superior

## Base URL de ambientes

Producción

https://admin.paguelofacil.com/

Pruebas

https://sandbox.paguelofacil.com/

Servicio GET

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)`/PFManagementServices/api/v1/MerchantTransactions/{Parameters}`

## Headers

| Parámetro     | Tipo   | Formato                                                                                          | Requerido | Descripción                                                                                                                                        |
| ------------- | ------ | ------------------------------------------------------------------------------------------------ | --------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authorization | String | [{your accessToken}](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) | Si        | Autenticación para acceso al servicio  [¿Cómo obtengo mi accessToken?](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) |

## Parámetros

| Parámetro   | Tipo    | Formato                                         | Requerido | Descripción                                                                                  |
| ----------- | ------- | ----------------------------------------------- | --------- | -------------------------------------------------------------------------------------------- |
| Filter      | String  | {field}::{value}\|{field2}::{value} Conditional | No        | Permite realizar filtros y consultas sobre el servicio. Los Query Param son no casesensitive |
| Conditional | String  |                                                 | No        | {field}{operator}{value}\|{field2}{operator $bt}{value}::{value2}                            |
| Limit       | Integer |                                                 | No        | Indica el numero maximo de resultados esperados                                              |
| Offset      | Integer |                                                 | No        | Indica desde donde se retornara la consulta, esto es usado para paginacion                   |
| Sort        | String  | {field},{-field2}                               | No        | Permite ordenar la consulta, si quiere hacer un order descendente use - antes del campo      |
| Field       | String  | {field},{field2::{Operator}}                    | No        | Permite retornar solo los campos indicados, y aplicar operaciones sobre estos                |

## Ejemplos

*   [Por Campo](#field)
*   [Rango de fechas](#filter)
*   [Utilizando Operatores](#operator)

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)

`///Ejemplo codOper https://admin.paguelofacil.com/PFManagementServices/api/v1/MerchantTransactions?filter=codOper::AUTH_CAP-TL22CD        //Ejemplo "customFields"  https://admin.paguelofacil.com/PFManagementServices/api/v1/MerchantTransactions?conditional=customFields%24lk%25tramiteId%25%7CcustomFields%24lk%25TID2345%25&limit=1`

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`https://admin.paguelofacil.com/PFManagementServices/api/v1/MerchantTransactions?conditional=dateTms$bt2020-05-27T00:00:00::2020-05-28T23:59:59`

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`https://admin.paguelofacil.com/PFManagementServices/api/v1/MerchantTransactions?field=idTransaction::COUNT`

---

1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  Estaciones

# Estaciones de Pago

A través de nuestas API's puedes generar y consultar transacciones pendientes  a estaciones de pago para que los usuarios de **PagueloFacil App**  puedan pagar.

## Prerrequisitos

CCLW

Llaves de conexión al API

Certificado SSL

TLS 1.3 o superior

## Base URL de ambientes

Producción

https://admin.paguelofacil.com/

Pruebas

https://sandbox.paguelofacil.com/

## Headers

| Parámetro     | Tipo   | Formato                                                                                          | Requerido | Descripción                                                                                                                                        |
| ------------- | ------ | ------------------------------------------------------------------------------------------------ | --------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authorization | String | [{your accessToken}](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) | Si        | Autenticación para acceso al servicio  [¿Cómo obtengo mi accessToken?](http://developers.paguelofacil.com/faq/detalle/como-obtengo-mi-accesstoken) |

## Asignar un pago

Servicio POST

/PFManagementServices/api/v1/api/v1/Activities/

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`{   "description" : "Pago Mesa 2 Orden ABC",   "amount" : 10.0,   "discount" : 0.0,   "idPaymentStation" : 4 // A que estacion asignaras un pago }`

## Cancelar un pago

Puede funcionar para pagos que fueron creados por error, o se necesita corregir alguna información o ya no se desea recibir el pago por el usuario

Servicio POST

/PFManagementServices/api/v1/api/v1/CancelActivity/

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`//Ejemplo del cuerpo de la Petición {   "reason" : "Error de Carga",   "idActivity" : 3513, // Actividad devuelta en la creación del pago, si la perdió puede hacer la búsqueda de un pago pendiente, como en el primer ejemplo   "amount" : 10.0 }`

## Validar pago

Verificar si la actividad fue pagado por un usuario.

Servicio GET

/PFManagementServices/api/v1/api/v1/Activities

### Parámetros

| Parámetro  | Tipo    | Formato | Requerido | Descripción                                              |
| ---------- | ------- | ------- | --------- | -------------------------------------------------------- |
| status     | integer | 2       | Si        | El estado de una transacción pagada en "2"               |
| idActivity | integer | 23781   | Si        | Id retornado en la solicitud de creación de la actividad |

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)
`//Ejemplo de Petición https://admin.paguelofacil.com/PFManagementServices/api/v1/Activities?conditional=status::2|idActivity::{_YOUR_ID_ACTIVITY_}`

---

1.  [Inicio](http://developers.paguelofacil.com/ "Inicio")
2.  [API](http://developers.paguelofacil.com/api "API")
3.  PagoCash

# Pago Cash

Es uno de los otros servicio que tiene Paguelofacil para la comodidad de nuestros clientes afiliados, abarcando aquellos clientes que no poseen una tarjeta de crédito.  
Pago-Cash consiste en que los clientes pueden pagar los servicios de su comercio a travez de la red de kioscos [Punto Pago](http://www.puntopago.net) y automáticamente se le ve relejado en su cuenta de Paguelofacil.

**Nota —** Las pruebas de está integración solo pueden ser contra el ambiente de producción porque no es posible emular el pago a través de las máquinas de Punto Pago.

## Prerrequisitos

CCLW

Llaves de conexión al API

Certificado SSL

TLS 1.3 o superior

## Base URL de ambientes

Producción

https://secure.paguelofacil.com/  
https://api.pfserver.net/

Pruebas

https://sandbox.paguelofacil.com/  
https://api-sand.pfserver.net/

**IMPORTANTE —** PagueloFacil tiene credenciales para [ambiente de pruebas](https://demo.paguelofacil.com) y credenciales para [ambiente de producción](https://comercios.paguelofacil.com) que permiten integrar los métodos de pago, no se deben confundir al momento de realizar las configuraciones, cada ambiente requiere de sus credenciales. El monto mímo es de $ 1.00 y la moneda permitida es USD.

Servicio POST

/webservices/rest/regCashTx

## Flujo de Pago

![Proceso PagoCash](/src/Frontend/Files/MediaLibrary/05/pagocash-pasos.png)

## Solicitar un Código (Request)

Debes hacer la solicitud de un PP de pago, indicando la información correspondiente del pago

Ejemplo, `php` del código de generación de PP.

![Copy to clipboard](https://developers.paguelofacil.com/src/Frontend/Themes/Paguelofacil/Core/Layout/img/clippy.svg)

 `$restUrl = "https://sandbox.paguelofacil.com/webservices/rest/regCashTx";    $amount = "1.00"; //amount total for transaction  $email = "alabrito@gmail.com"; //customer email  $phone = "+50766666666"; //customer phone  $description = "Pay for cash"; //Description for pay  $cclw = "18054582121180545821211805458212118054582121180545821211805458212118054582121"; //Paguelofacil code    $cashExpired = null;  //$cashExpired = 10; //duration in minutes for link payment (ex: 10 minutes)     $data = Array(       "amount" => $amount,       "email" => $email,       "phone" => $phone,       "concept" => $description,       "idUsrRegTx" => null,       "cclw" => $cclw,       "cashExpired" => $cashExpired,       "customFieldValues"  => [["id"=>"idOrder","nameOrLabel"=>"Nro de Orden","value"=>"OD-234567"],                             ["id"=>"idUser","nameOrLabel"=>"User","value"=>"24"],                               ["id"=>"idTx","nameOrLabel"=>"Txtx","value"=>"678643"],                                 ["id"=>"reference","nameOrLabel"=>"Referencia","value"=>"6754"],                             ["id"=>"activo","nameOrLabel"=>"estado","value"=>"true"]],     );    $jsonR = json_encode($data);                   $ch = curl_init (); curl_setopt ($ch, CURLOPT_URL,  $restUrl);       curl_setopt ($ch, CURLOPT_POST, true); curl_setopt ($ch, CURLOPT_AUTOREFERER, true); curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true); curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Accept: */*')); curl_setopt ($ch, CURLOPT_POSTFIELDS, $jsonR);  $result = curl_exec($ch);              if($result){     $result = json_decode($result,true); //transform response in array          if(array_key_exists("code",$result['headerStatus']) == 200){         //is processed the request         //ex for response message: 1|PP735886          $data = $result['data'];         $code = substr($data, 2); //operation code         $status = substr($data, 0, -9); //status 1|0 -> for the code generation                  echo $code; // show the code for pagocash              }     else{         //dont get a code for paguelofacil         //show an error message         die("service error message");     } } else{     //cannot connect with the service in paguelofacil (error message)     die("connection error message"); }`
                   

## Respuesta (URL de Retorno)

**Nota —** Para configurar la URL de retorno de PagoCash deberá solicitarlo a través de soporte o del formulario de soporte en https://comercios.paguelofacil.com.

Paguelofacil  envia la respuesta una vez su cliente haya realizado el pago a través del kiosco de punto de pago, la respuesta la recibe a la url de retorno través de una variable REQUEST "CodOper".

En la respuesta se envía el monto recibido en cada operación. Una transacción puede tener varias operaciones por parte del usuario, debido a que el usuario puede hacer uso del mismo código tantas veces como sea necesario hasta completar el monto de la transacción, siempre que el código de operación (PP) se mantenga vigente. 

Cada operación que realice el usuario en la máquina se registra como una nueva transacción en Paguelofacil y una vez que completa el monto este código no puede ser utilizado. En la respuesta siempre se envía el código de operación "CodOper" que se emitió en el request.

Es necesario que al momento de procesar la respuesta valide que el monto pagado "TotalPay" sea igual o mayor  al monto solicitado "amount" para completar la compra (en ocasiones puede ser mayor ej. 10.35 el usuario paga 11.00). En el caso de que el monto sea menor, se aconseja permitir varias transacciones por pedido y acumular los montos recibidos y una vez que las transacciones completen el monto del pedido proceder a completarlo.

## Parámetros de respuesta

| Parámetro | Descripción                                                                                                                       |
| --------- | --------------------------------------------------------------------------------------------------------------------------------- |
| TotalPay  | Monto Pagado por el usuario, este monto se debe validar porque es posible que realice varios pagos para completar el monto total. |
| Fecha     | Fecha de la transacción en formato dd/mm/yyyy                                                                                     |
| Hora      | Hora de la transacción en formato HH:MM.SS                                                                                        |
| CodOper   | Numero de Operación alfanumérico                                                                                                  |