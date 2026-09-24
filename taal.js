/*
 * Las Tapas - taal.js
 * Vertaalt elke pagina naar Nederlands (nl), Engels (en) of Spaans (es).
 *
 * Hoe het werkt:
 *  - Alle teksten op de pagina worden opgezocht in het woordenboek hieronder (op basis van de Nederlandse tekst).
 *  - Teksten met een getal of naam erin worden vertaald met patronen, bijv. "Tafel {1}" -> "Table {1}".
 *  - Nieuwe teksten (nieuwe bestellingen, meldingen, pop-ups) worden automatisch meteen vertaald.
 *  - De gekozen taal wordt per apparaat onthouden.
 *
 * Gebruik op een pagina: <script src="taal.js"></script> en een <span id="taal-plek"></span> waar de knop moet komen.
 * Een tekst niet in het woordenboek? Dan blijft hij gewoon Nederlands. Voeg hem hieronder toe: 'Nederlands': ['English', 'Español'].
 */
(function () {
    'use strict';

    const TALEN = {
        nl: { vlag: '🇳🇱', naam: 'Nederlands' },
        en: { vlag: '🇬🇧', naam: 'English' },
        es: { vlag: '🇪🇸', naam: 'Español' },
    };

    // ======================= WOORDENBOEK (vaste teksten) =======================
    const W = {
        // ---------- Algemeen ----------
        'Annuleren': ['Cancel', 'Cancelar'],
        'Opslaan': ['Save', 'Guardar'],
        'Verwijderen': ['Delete', 'Eliminar'],
        'Aanpassen': ['Edit', 'Editar'],
        'Terug': ['Back', 'Volver'],
        'Uitloggen': ['Log out', 'Cerrar sesión'],
        'Laden': ['Loading', 'Cargando'],
        'Status': ['Status', 'Estado'],
        'Naam': ['Name', 'Nombre'],
        'Tafel': ['Table', 'Mesa'],
        'Tafels': ['Tables', 'Mesas'],
        'Totaal': ['Total', 'Total'],
        'Gast': ['Guest', 'Cliente'],
        'Gasten': ['Guests', 'Clientes'],
        'Gast naam': ['Guest name', 'Nombre del cliente'],
        'Gezelschap': ['Party', 'Grupo'],
        'Arrangement': ['Package', 'Menú'],
        'Arrangementen': ['Packages', 'Menús'],
        'Categorie': ['Category', 'Categoría'],
        'Omschrijving': ['Description', 'Descripción'],
        'Volgorde': ['Order', 'Orden'],
        'Door': ['By', 'Por'],
        'Tijd': ['Time', 'Hora'],
        'Open': ['Open', 'Libre'],
        'Bezet': ['Occupied', 'Ocupada'],
        'Onbekend': ['Unknown', 'Desconocido'],
        'Onbekend arrangement': ['Unknown package', 'Menú desconocido'],
        'Welkom': ['Welcome', 'Bienvenido/a'],
        'Eigenaar': ['Owner', 'Propietario'],
        'Ingelogd als': ['Logged in as', 'Sesión iniciada como'],
        'Geluid uit': ['Sound off', 'Sonido desactivado'],
        'Geluid aan': ['Sound on', 'Sonido activado'],
        'Keuken': ['Kitchen', 'Cocina'],
        'Bediening': ['Service', 'Sala'],
        'Beheerderspaneel': ['Admin panel', 'Panel de administración'],
        'Voorraad': ['Stock', 'Existencias'],
        'Kan de server niet bereiken': ['Cannot reach the server', 'No se puede conectar con el servidor'],
        'Bijv. Jan de Vries': ['E.g. John Smith', 'Ej. Juan García'],

        // ---------- Allergenen ----------
        'Allergenen': ['Allergens', 'Alérgenos'],
        'Gluten': ['Gluten', 'Gluten'],
        'Schaaldieren': ['Crustaceans', 'Crustáceos'],
        'Ei': ['Egg', 'Huevo'],
        'Vis': ['Fish', 'Pescado'],
        'Pinda': ['Peanut', 'Cacahuete'],
        'Soja': ['Soy', 'Soja'],
        'Melk': ['Milk', 'Leche'],
        'Noten': ['Nuts', 'Frutos secos'],
        'Selderij': ['Celery', 'Apio'],
        'Mosterd': ['Mustard', 'Mostaza'],
        'Sesam': ['Sesame', 'Sésamo'],
        'Sulfiet': ['Sulphites', 'Sulfitos'],
        'Lupine': ['Lupin', 'Altramuces'],
        'Weekdieren': ['Molluscs', 'Moluscos'],

        // ---------- Categorieën ----------
        'Voorgerechten': ['Starters', 'Entrantes'],
        'Warme tapas': ['Hot tapas', 'Tapas calientes'],
        'Warme Tapas': ['Hot tapas', 'Tapas calientes'],
        'Drankjes': ['Drinks', 'Bebidas'],
        'Toetjes': ['Desserts', 'Postres'],
        'alles': ['all', ''],
        'voorgerechten': ['starters', ''],
        'warm': ['hot', ''],
        'warme tapas': ['hot tapas', ''],
        'drankjes': ['drinks', ''],
        'toetjes': ['desserts', ''],

        // ---------- Menu-omschrijvingen (standaardmenu) ----------
        'Geroosterde boerenboterham met verse tomaat, knoflook en olijfolie': ['Toasted country bread with fresh tomato, garlic and olive oil', 'Pan de pueblo tostado con tomate fresco, ajo y aceite de oliva'],
        'Spaanse manzanilla olijven met blokjes oude Manchego kaas': ['Spanish manzanilla olives with cubes of aged Manchego cheese', 'Aceitunas manzanilla con dados de queso manchego curado'],
        'Authentieke gedroogde Spaanse serranoham': ['Authentic cured Spanish Serrano ham', 'Auténtico jamón serrano curado'],
        'Krokante aardappeltjes met pittige brava-saus en huisgemaakte aioli': ['Crispy potatoes with spicy brava sauce and homemade aioli', 'Patatas crujientes con salsa brava picante y alioli casero'],
        'Sissende knoflookgarnalen met chilipeper en verse peterselie': ['Sizzling garlic prawns with chilli and fresh parsley', 'Gambas chisporroteantes al ajillo con guindilla y perejil fresco'],
        'Spaanse rundergehaktballetjes in rijke tomaten-kruidensaus': ['Spanish beef meatballs in a rich tomato and herb sauce', 'Albóndigas de ternera en salsa de tomate y hierbas'],
        'Traditionele saffraanrijst met malse kip en mediterrane groenten': ['Traditional saffron rice with tender chicken and Mediterranean vegetables', 'Arroz tradicional con azafrán, pollo tierno y verduras mediterráneas'],
        'Romige hamkroketjes met een krokante korst (4 stuks)': ['Creamy ham croquettes with a crispy coating (4 pieces)', 'Cremosas croquetas de jamón con rebozado crujiente (4 unidades)'],
        'Pikante Spaanse chorizo worst gestoofd in rode wijn': ['Spicy Spanish chorizo braised in red wine', 'Chorizo picante guisado en vino tinto'],
        'Heerlijke frisse karaf huisgemaakte traditionele sangría': ['A refreshing carafe of homemade traditional sangría', 'Jarra fresca de sangría tradicional casera'],
        'Spaanse zomerse cocktail van rode wijn met citroenlimonade': ['Spanish summer cocktail of red wine with lemon soda', 'Cóctel veraniego de vino tinto con gaseosa de limón'],
        'Koud verfrissend Spaans tapbier (30cl)': ['Cold refreshing Spanish draught beer (30cl)', 'Caña de cerveza española bien fría (30cl)'],
        'Cocktail met cava, gin, wodka en vers sinaasappelsap': ['Cocktail with cava, gin, vodka and fresh orange juice', 'Cóctel de cava, ginebra, vodka y zumo de naranja natural'],
        'Krokante Spaanse churros met dikke warme chocoladesaus': ['Crispy Spanish churros with thick hot chocolate sauce', 'Churros crujientes con chocolate caliente espeso'],
        'Fluwelen Spaanse vanillepudding met een gekaramelliseerd suikerlaagje': ['Velvety Spanish vanilla custard with a caramelised sugar top', 'Suave crema de vainilla con costra de azúcar caramelizado'],
        'Ambachtelijk Spaans noga-ijs met amandelsnippers': ['Artisanal Spanish nougat ice cream with almond flakes', 'Helado artesanal de turrón con almendra laminada'],

        // ---------- Arrangementen (standaard) ----------
        'Onbeperkt tapas eten. Drankjes kun je los bijbestellen': ['Unlimited tapas. Drinks can be ordered separately', 'Tapas ilimitadas. Las bebidas se piden aparte'],
        'Onbeperkt tapas eten + Spaanse drankjes': ['Unlimited tapas + Spanish drinks', 'Tapas ilimitadas + bebidas españolas'],
        '3 uur lang onbeperkt genieten van eten én drankjes': ['3 hours of unlimited food and drinks', '3 horas de comida y bebida ilimitadas'],

        // ---------- Klant: kop, tabs, menu ----------
        'Taberna española · all you can eat': ['Spanish taberna · all you can eat', 'Taberna española · todo lo que puedas comer'],
        'Serveerster roepen': ['Call a waiter', 'Llamar al camarero'],
        'Hulp': ['Help', 'Ayuda'],
        'Klaar, ik wil betalen': ['Done, I want to pay', 'Listo, quiero pagar'],
        'Betalen': ['Pay', 'Pagar'],
        'Het Menu': ['The Menu', 'La Carta'],
        'Mijn Bestellingen': ['My Orders', 'Mis Pedidos'],
        'Live': ['Live', 'En directo'],
        'Onze kaart · onbeperkt tapas': ['Our menu · unlimited tapas', 'Tapas ilimitadas'],
        'Allergie of dieetwens? Zet het in de opmerking bij je bestelling of vraag het personeel': ['Allergy or dietary need? Add it to the note with your order or ask our staff', '¿Alergia o dieta especial? Indícalo en la nota de tu pedido o pregunta al personal'],
        'Menu laden': ['Loading menu', 'Cargando la carta'],
        'niet inbegrepen': ['not included', 'no incluido'],
        'Uitverkocht': ['Sold out', 'Agotado'],
        'Jouw bestelling': ['Your order', 'Lo que vas a pedir'],
        'Je mandje is nog leeg': ['Your basket is still empty', 'Tu pedido está vacío'],
        'Totaal items': ['Total items', 'Total de artículos'],
        'Drankjes (niet inbegrepen)': ['Drinks (not included)', 'Bebidas (no incluidas)'],
        'Opmerking of allergie (optioneel)': ['Note or allergy (optional)', 'Nota o alergia (opcional)'],
        'Bijv. zonder knoflook, notenallergie': ['E.g. no garlic, nut allergy', 'Ej. sin ajo, alergia a los frutos secos'],
        'Pedir Ahora! (Bestel)': ['Pedir Ahora! (Order)', 'Pedir Ahora!'],
        'Tijd is om: verleng om te bestellen': ['Time is up: extend to order', 'Se acabó el tiempo: amplía para pedir'],
        'Bestelling versturen': ['Sending order', 'Enviando pedido'],
        'Olé! Bestelling verzonden naar de keuken': ['Olé! Order sent to the kitchen', '¡Olé! Pedido enviado a la cocina'],
        'Verstuurd naar de keuken': ['Sent to the kitchen', 'Enviado a la cocina'],
        'Tik op + om iets te kiezen': ['Tap + to choose something', 'Pulsa + para elegir algo'],
        '1 item gekozen': ['1 item chosen', '1 artículo elegido'],
        'Bekijken': ['View', 'Ver'],
        'Mandje sluiten': ['Close basket', 'Cerrar pedido'],
        'Nog geen bestellingen geplaatst vanuit deze tafel': ['No orders placed from this table yet', 'Todavía no se ha hecho ningún pedido desde esta mesa'],
        'In behandeling bij de keuken': ['Being prepared in the kitchen', 'Preparándose en la cocina'],
        'Klaar voor bezorging': ['Ready to be served', 'Listo para servir'],
        'Onderweg naar jouw tafel': ['On its way to your table', 'De camino a tu mesa'],
        'Bezorgd / Eet smakelijk': ['Served / Enjoy your meal', 'Servido / ¡Buen provecho'],
                'Jullie bestellingen, live bijgewerkt': ['Your orders, updated live', 'Vuestros pedidos, actualizados en directo'],
        'Bestellingen laden': ['Loading orders', 'Cargando pedidos'],
        'Jullie hebben nog niets besteld': ['You have not ordered anything yet', 'Todavía no habéis pedido nada'],
        'Nog geen bestellingen voor deze tafel': ['No orders for this table yet', 'Aún no hay pedidos para esta mesa'],
        'Buen provecho': ['Buen provecho! (Enjoy your meal)', 'Buen provecho'],
        'Las Tapas · Taberna española · Allergie of vraag? Tik op 🙋 bovenin': ['Las Tapas · Spanish taberna · Allergy or question? Tap 🙋 at the top', 'Las Tapas · Taberna española · ¿Alergia o pregunta? Pulsa 🙋 arriba'],

        // Status van een bestelling
        'nieuw': ['new', 'nuevo'],
        'bezig': ['in progress', 'en preparación'],
        'klaar': ['ready', 'listo'],
        'onderweg': ['on its way', 'en camino'],
        'bezorgd': ['delivered', 'servido'],
        'Nieuw': ['New', 'Nuevo'],
        'In bereiding': ['Being prepared', 'En preparación'],
        'Klaar': ['Ready', 'Listo'],
        'Onderweg': ['On its way', 'En camino'],
        'Bezorgd': ['Delivered', 'Servido'],
        'Ontvangen': ['Received', 'Recibido'],
        'Wordt bereid': ['Being prepared', 'Preparándose'],
        'Klaar om te serveren': ['Ready to serve', 'Listo para servir'],
        'Wordt gebracht': ['On its way', 'De camino a la mesa'],
        'Staat op tafel': ['Served', 'En la mesa'],

        // ---------- Klant: welkom ----------
        'Bienvenidos a Las Tapas': ['Bienvenidos a Las Tapas! (Welcome)', 'Bienvenidos a Las Tapas'],
        'Vul je gegevens in om het menu te bekijken': ['Enter your details to see the menu', 'Introduce tus datos para ver la carta'],
        'Jouw naam (Hoofdboeker)': ['Your name (main booker)', 'Tu nombre (titular de la reserva)'],
        'Volwassenen': ['Adults', 'Adultos'],
        'Senioren (65+)': ['Seniors (65+)', 'Mayores (65+)'],
        'Kinderen': ['Children', 'Niños'],
        'Kies jullie arrangement': ['Choose your package', 'Elegid vuestro menú'],
        'Arrangementen laden': ['Loading packages', 'Cargando menús'],
        'Vul eerst je naam in': ['Please enter your name first', 'Introduce primero tu nombre'],
        'Kies minimaal 1 persoon': ['Choose at least 1 person', 'Elige al menos 1 persona'],
        'Deze QR-code werkt niet (meer)': ['This QR code no longer works', 'Este código QR ya no funciona'],
        'Scan de QR-code die op jullie tafel staat, of vraag het personeel om hulp': ['Scan the QR code on your table, or ask our staff for help', 'Escanead el código QR de vuestra mesa o pedid ayuda al personal'],

        // ---------- Klant: afgesloten bezoek en aansluiten ----------
        'Jullie vorige bezoek aan deze tafel is afgesloten. Welkom! Vul jullie gegevens opnieuw in': ['Your previous visit to this table has been closed. Welcome! Please enter your details again', 'Vuestra visita anterior a esta mesa se ha cerrado. ¡Bienvenidos! Introducid de nuevo vuestros datos'],
        'Deze tafel is al in gebruik': ['This table is already in use', 'Esta mesa ya está ocupada'],
        'Hoor je bij dit gezelschap': ['Are you part of this group', '¿Formas parte de este grupo'],
        'Ja, aansluiten': ['Yes, join', 'Sí, unirme'],
        'Nee, dit is niet mijn tafel': ['No, this is not my table', 'No, esta no es mi mesa'],
        'Scan de QR-code die op je eigen tafel staat, of vraag het personeel om hulp': ['Scan the QR code on your own table, or ask our staff for help', 'Escanea el código QR de tu propia mesa o pide ayuda al personal'],
        'Aansluiten is niet gelukt': ['Joining failed', 'No se ha podido unir'],
        'een gezelschap': ['a group', 'un grupo'],
        'Jullie bezoek aan deze tafel is afgesloten. Meld je opnieuw aan': ['Your visit to this table has been closed. Please register again', 'Vuestra visita a esta mesa se ha cerrado. Registraos de nuevo'],
        'Deze tafel is niet (meer) in gebruik. Meld je opnieuw aan': ['This table is not (or no longer) in use. Please register again', 'Esta mesa no está (ya) en uso. Regístrate de nuevo'],

        // ---------- Klant: tijd en verlengen ----------
        'Jullie tijd begint bij de eerste bestelling': ['Your time starts with the first order', 'Vuestro tiempo empieza con el primer pedido'],
        'Jullie kunnen dineren tot': ['You can dine until', 'Podéis cenar hasta las'],
        'Jullie tijd zit erop': ['Your time is up', 'Se ha acabado vuestro tiempo'],
        'Tijd verlengen': ['Extend time', 'Ampliar tiempo'],
        'We hopen dat het heeft gesmaakt! Willen jullie nog wat langer blijven': ['We hope you enjoyed it! Would you like to stay a little longer', '¡Esperamos que os haya gustado! ¿Queréis quedaros un poco más'],
        'Verlengen met': ['Extending by', 'Ampliar'],
        'Jullie kunnen dan dineren tot': ['You can then dine until', 'Podréis cenar hasta las'],
        'Afrekenen': ['Pay the bill', 'Pedir la cuenta'],
        'Terug naar het menu': ['Back to the menu', 'Volver a la carta'],
        'Niet nu': ['Not now', 'Ahora no'],
        'Verlengen': ['Extend', 'Ampliar'],
        'Verlengen is niet gelukt': ['Extending failed', 'No se ha podido ampliar'],
        'Jullie tijd start pas bij de eerste bestelling': ['Your time only starts with the first order', 'Vuestro tiempo empieza con el primer pedido'],
        'Jullie tijd zit erop. Verleng de tijd om nog iets te bestellen': ['Your time is up. Extend your time to order more', 'Se ha acabado vuestro tiempo. Ampliadlo para seguir pidiendo'],
        'Deze ronde nog': ['Still available this round:', 'Quedan en esta ronda:'],
        'Deze ronde is vol. Vanaf': ['This round is full. From', 'Esta ronda está completa. A partir de las'],
        'kunnen jullie weer gerechten bestellen. Drankjes kunnen altijd': ['you can order dishes again. Drinks are always possible', 'podréis volver a pedir platos. Las bebidas se pueden pedir siempre'],
        'Deze ronde is vol. Drankjes kunnen altijd': ['This round is full. Drinks are always possible', 'Esta ronda está completa. Las bebidas se pueden pedir siempre'],

        // ---------- Klant: betalen ----------
        'Kies je betaalmethode': ['Choose how to pay', 'Elige cómo pagar'],
        'Hoe willen jullie de rekening voldoen': ['How would you like to pay the bill', '¿Cómo queréis pagar la cuenta'],
        'Online betalen': ['Pay online', 'Pagar online'],
        'Contant betalen': ['Pay in cash', 'Pagar en efectivo'],
        'Scan om te betalen': ['Scan to pay', 'Escanea para pagar'],
        'Scan deze QR-code met je telefoon om de betaling af te ronden': ['Scan this QR code with your phone to complete the payment', 'Escanea este código QR con tu móvil para completar el pago'],
        'Tik na het betalen op de knop hieronder. Een serveerster komt daarna even controleren of de betaling gelukt is': ['After paying, tap the button below. A waiter will then come to check that the payment went through', 'Después de pagar, pulsa el botón de abajo. Un camarero vendrá a comprobar que el pago se ha realizado'],
        'Ik heb betaald': ['I have paid', 'Ya he pagado'],
        'Betaling controleren': ['Checking payment', 'Comprobando el pago'],
        'Verzoek ontvangen': ['Request received', 'Solicitud recibida'],
        'Serveerster onderweg': ['Waiter on the way', 'Camarero en camino'],
        'Een moment geduld. Dit scherm sluit automatisch zodra de betaling is afgerond': ['One moment please. This screen closes automatically once the payment is complete', 'Un momento, por favor. Esta pantalla se cerrará sola cuando se complete el pago'],
        'Bedankt! De bediening heeft een melding ontvangen. Een serveerster komt even langs om te controleren of de betaling gelukt is': ['Thank you! Our staff have been notified. A waiter will come by to check that the payment went through', '¡Gracias! El personal ha recibido un aviso. Un camarero pasará a comprobar que el pago se ha realizado'],
        'De bediening heeft een melding ontvangen dat jullie contant willen betalen. Zodra een serveerster het verzoek accepteert, zie je dat hier': ['Our staff have been notified that you want to pay in cash. As soon as a waiter accepts the request, you will see it here', 'El personal ha recibido un aviso de que queréis pagar en efectivo. En cuanto un camarero acepte la solicitud, lo veréis aquí'],
        'Betaalverzoek versturen is niet gelukt': ['Sending the payment request failed', 'No se ha podido enviar la solicitud de pago'],
        'Te betalen': ['To pay', 'A pagar'],
        'Volwassene': ['Adult', 'Adulto'],
        'Senior (65+)': ['Senior (65+)', 'Mayor (65+)'],
        'Kind': ['Child', 'Niño'],

        // ---------- Bon ----------
        'Wil je een bon': ['Would you like a receipt', '¿Quieres un recibo'],
        'Geen bon': ['No receipt', 'Sin recibo'],
        'Per e-mail': ['By email', 'Por correo'],
        'Op papier': ['On paper', 'En papel'],
        'De bon wordt gemaild zodra de betaling is afgerond': ['The receipt will be emailed once the payment is complete', 'El recibo se enviará por correo cuando se complete el pago'],
        'De serveerster neemt een papieren bon voor je mee': ['The waiter will bring you a paper receipt', 'El camarero te traerá un recibo en papel'],
        'Vul een geldig e-mailadres in voor de bon': ['Please enter a valid email address for the receipt', 'Introduce un correo electrónico válido para el recibo'],
        'De bon wordt gemaild naar': ['The receipt will be emailed to', 'El recibo se enviará a'],
        'De serveerster neemt een papieren bon mee': ['The waiter will bring a paper receipt', 'El camarero traerá un recibo en papel'],
        'Bon printen': ['Print receipt', 'Imprimir recibo'],
        'Bon per e-mail naar': ['Receipt by email to', 'Recibo por correo a'],
        'gaat automatisch bij vrijmaken': ['sent automatically when freeing up the table', 'se envía automáticamente al liberar la mesa'],
        'Wil een papieren bon: print hem en neem hem mee': ['Wants a paper receipt: print it and bring it along', 'Quiere un recibo en papel: imprímelo y llévalo'],
        'E-mail voor de bon': ['Email for receipts', 'Correo para los recibos'],
        'E-mailadres (afzender)': ['Email address (sender)', 'Correo electrónico (remitente)'],
        'App-wachtwoord': ['App password', 'Contraseña de aplicación'],
        'Naam afzender': ['Sender name', 'Nombre del remitente'],
        'Mailserver': ['Mail server', 'Servidor de correo'],
        'Poort': ['Port', 'Puerto'],
        'Beveiliging': ['Security', 'Seguridad'],
        'Certificaat niet controleren': ['Do not verify certificate', 'No comprobar el certificado'],
        'Testmail sturen naar': ['Send test email to', 'Enviar correo de prueba a'],
        'leeg = naar het afzenderadres': ['empty = to the sender address', 'vacío = a la dirección del remitente'],
        'Opslaan & testmail sturen': ['Save & send test email', 'Guardar y enviar correo de prueba'],

        // ---------- Klant: serveerster roepen ----------
        'Waarmee kunnen we helpen? Kies een onderwerp': ['How can we help? Choose a topic', '¿En qué podemos ayudar? Elige un tema'],
        'Ik heb een vraag': ['I have a question', 'Tengo una pregunta'],
        'Probleem met bestelling': ['Problem with my order', 'Problema con el pedido'],
        'Allergie of dieet': ['Allergy or diet', 'Alergia o dieta'],
        'Iets anders': ['Something else', 'Otra cosa'],
        'Toelichting (optioneel)': ['Details (optional)', 'Detalles (opcional)'],
        'Bijv. we missen nog de patatas bravas': ['E.g. we are still missing the patatas bravas', 'Ej. nos faltan las patatas bravas'],
        'Roep een serveerster': ['Call a waiter', 'Llamar a un camarero'],
        'Je oproep is verstuurd. Een serveerster komt zo snel mogelijk': ['Your call has been sent. A waiter will come as soon as possible', 'Tu llamada se ha enviado. Un camarero vendrá lo antes posible'],
        'Oproep afgehandeld': ['Request handled', 'Solicitud atendida'],
        'Vul eerst je naam in en kies een arrangement': ['Please enter your name and choose a package first', 'Introduce primero tu nombre y elige un menú'],
        'Roepen is niet gelukt': ['Calling failed', 'No se ha podido llamar'],
        'Annuleren is niet gelukt': ['Cancelling failed', 'No se ha podido cancelar'],
        'Er is al een serveerster geroepen voor deze tafel': ['A waiter has already been called for this table', 'Ya se ha llamado a un camarero para esta mesa'],

        // ---------- Klant: beoordeling ----------
        'Hoe was het bij Las Tapas': ['How was your visit to Las Tapas', '¿Qué tal en Las Tapas'],
        'Bedankt voor jullie bezoek! Laat je weten hoe je het vond': ['Thank you for your visit! Would you tell us what you thought', '¡Gracias por vuestra visita! ¿Nos contáis qué os ha parecido'],
        'Tik op de sterren': ['Tap the stars', 'Pulsa las estrellas'],
        'Slecht': ['Poor', 'Malo'],
        'Matig': ['Fair', 'Regular'],
        'Goed': ['Good', 'Bueno'],
        'Erg goed': ['Very good', 'Muy bueno'],
        'Wil je nog iets kwijt? (optioneel)': ['Anything else you would like to share? (optional)', '¿Algo más que quieras contarnos? (opcional)'],
        'Wat vond je lekker, wat kan beter': ['What did you enjoy, what could be better', '¿Qué te ha gustado y qué podemos mejorar'],
        'Beoordeling versturen': ['Send review', 'Enviar valoración'],
        'Overslaan': ['Skip', 'Omitir'],
        'Bedankt voor je beoordeling. Graag tot ziens bij Las Tapas': ['Thank you for your review. We hope to see you again at Las Tapas', 'Gracias por tu valoración. ¡Hasta pronto en Las Tapas'],
        'Er is geen recent bezoek gevonden om te beoordelen': ['No recent visit was found to review', 'No se ha encontrado ninguna visita reciente para valorar'],
        'Kies 1 tot 5 sterren': ['Choose 1 to 5 stars', 'Elige de 1 a 5 estrellas'],
        '1 ster': ['1 star', '1 estrella'],

        // ---------- Servermeldingen ----------
        'Deze QR-code is ongeldig of verouderd. Vraag het personeel om hulp': ['This QR code is invalid or outdated. Please ask our staff for help', 'Este código QR no es válido o está caducado. Pide ayuda al personal'],
        'Meld je eerst aan bij deze tafel (naam en arrangement kiezen)': ['Please register at this table first (enter name and choose a package)', 'Regístrate primero en esta mesa (nombre y menú)'],
        'Meld je eerst aan bij deze tafel': ['Please register at this table first', 'Regístrate primero en esta mesa'],
        'Jullie zijn al aan het afrekenen': ['You are already paying', 'Ya estáis pagando'],
        'Je mandje is leeg': ['Your basket is empty', 'Tu pedido está vacío'],
        'Er loopt al een betaling voor deze tafel': ['A payment is already in progress for this table', 'Ya hay un pago en curso para esta mesa'],
        'Deze tafel is niet in gebruik': ['This table is not in use', 'Esta mesa no está en uso'],
        'Er is geen open betaalverzoek voor deze tafel': ['There is no open payment request for this table', 'No hay ninguna solicitud de pago abierta para esta mesa'],
        'Geen toegang: log in als medewerker om dit te doen': ['No access: log in as staff to do this', 'Sin acceso: inicia sesión como empleado para hacerlo'],
        'Geef minimaal 1 persoon op': ['Enter at least 1 person', 'Indica al menos 1 persona'],
        'Onbekend arrangement': ['Unknown package', 'Menú desconocido'],
        'Bestelling niet gevonden': ['Order not found', 'Pedido no encontrado'],
        'Deze oproep bestaat niet meer (misschien geannuleerd door de gast)': ['This request no longer exists (perhaps cancelled by the guest)', 'Esta llamada ya no existe (quizá la canceló el cliente)'],
        'Fout bij bestellen': ['Error while ordering', 'Error al hacer el pedido'],

        // ---------- Chef ----------
        'Las Tapas - Cocina (Keuken)': ['Las Tapas - Cocina (Kitchen)', 'Las Tapas - Cocina'],
        'Live overzicht van binnenkomende Spaanse lekkernijen': ['Live overview of incoming Spanish delicacies', 'Resumen en directo de las delicias españolas entrantes'],
        'Openstaande Orders': ['Open Orders', 'Pedidos pendientes'],
        'Actieve Orders': ['Active Orders', 'Pedidos activos'],
        'Voltooide Orders': ['Completed Orders', 'Pedidos completados'],
        'Bezorgstatus': ['Delivery status', 'Estado de entrega'],
        'Tapa / Gerecht': ['Tapa / Dish', 'Tapa / Plato'],
        'Start Bereiding': ['Start preparing', 'Empezar a preparar'],
        'Gereed melden': ['Mark as ready', 'Marcar como listo'],
        'Klaar melden': ['Mark as ready', 'Marcar como listo'],
        'Weet je zeker dat je deze bestelling wilt verwijderen': ['Are you sure you want to delete this order', '¿Seguro que quieres eliminar este pedido'],
        'Fout bij opslaan van het gerecht': ['Error saving the dish', 'Error al guardar el plato'],
        'Verwijderen is niet gelukt': ['Deleting failed', 'No se ha podido eliminar'],
        'Geen openstaande bestellingen': ['No open orders', 'No hay pedidos pendientes'],
        'Status wordt beheerd door bediening (Verdwijnt 10 min na bezorging uit dit overzicht)': ['Status is managed by the service staff (Disappears from this overview 10 min after delivery)', 'El estado lo gestiona la sala (Desaparece de esta lista 10 min después de servir)'],
        'Status 1e Etage tafels': ['Status of 1st floor tables', 'Estado mesas 1ª planta'],
        'Status 2e Etage tafels': ['Status of 2nd floor tables', 'Estado mesas 2ª planta'],
        '1e Etage (Tafels 1 - 10)': ['1st floor (Tables 1 - 10)', '1ª planta (Mesas 1 - 10)'],
        '2e Etage (Tafels 11 - 20)': ['2nd floor (Tables 11 - 20)', '2ª planta (Mesas 11 - 20)'],
        '1e Etage': ['1st floor', '1ª planta'],
        '2e Etage': ['2nd floor', '2ª planta'],
        'Naar Serveerster Scherm': ['To service screen', 'A la pantalla de sala'],
        'Naar Keuken Scherm': ['To kitchen screen', 'A la pantalla de cocina'],
        'Naar Keuken': ['To kitchen', 'A la cocina'],

        'Aangepast door de keuken': ['Changed by the kitchen', 'Modificado por la cocina'],
        'Bestelling aanpassen': ['Edit order', 'Modificar pedido'],
        'Gerecht toevoegen': ['Add dish', 'Añadir plato'],
        'Alle gerechten zijn weggehaald. Bij opslaan wordt de hele bestelling verwijderd': ['All dishes have been removed. Saving will delete the whole order', 'Se han quitado todos los platos. Al guardar se eliminará todo el pedido'],
        'Deze bestelling is al klaar en kan niet meer worden aangepast': ['This order is already ready and can no longer be changed', 'Este pedido ya está listo y no se puede modificar'],
        'Minder': ['Less', 'Menos'],
        'Meer': ['More', 'Más'],
        'Weghalen': ['Remove', 'Quitar'],

        // ---------- Serveerster ----------
        'Las Tapas - Sala (Bediening)': ['Las Tapas - Sala (Service)', 'Las Tapas - Sala'],
        'Overzicht voor het uitserveren, bezorgen en betaalverzoeken': ['Overview for serving, delivering and payment requests', 'Resumen para servir, entregar y solicitudes de pago'],
        'Onderweg / Uitgeserveerd': ['On its way / Served', 'En camino / Servido'],
        'Betaalverzoeken': ['Payment requests', 'Solicitudes de pago'],
        'Oproepen': ['Calls', 'Llamadas'],
        'Meenemen naar tafel': ['Take to table', 'Llevar a la mesa'],
        'Bezorgd op tafel': ['Delivered to table', 'Servido en la mesa'],
        'Uitgeserveerd': ['Served', 'Servido'],
        'Verdwijnt 10 min na bezorging uit dit overzicht': ['Disappears from this overview 10 min after delivery', 'Desaparece de esta lista 10 min después de servir'],
        'Wil contant afrekenen aan tafel': ['Wants to pay in cash at the table', 'Quiere pagar en efectivo en la mesa'],
        'Online betaald: controleer of de betaling gelukt is': ['Paid online: check that the payment went through', 'Pagado online: comprueba que el pago se ha realizado'],
        'Betalen gewenst': ['Wants to pay', 'Quiere pagar'],
        'Wil betalen': ['Wants to pay', 'Quiere pagar'],
        'Betaling onderweg': ['Payment in progress', 'Pago en curso'],
        'WIL BETALEN': ['WANTS TO PAY', 'QUIERE PAGAR'],
        'BETALING ONDERWEG': ['PAYMENT IN PROGRESS', 'PAGO EN CURSO'],
        'TIJD IS OM': ['TIME IS UP', 'TIEMPO AGOTADO'],
        'ROEPT OM HULP': ['CALLING FOR HELP', 'PIDE AYUDA'],
        'Accepteren (ik kom naar de tafel)': ['Accept (I will go to the table)', 'Aceptar (voy a la mesa)'],
        'Accepteren (ik ga controleren)': ['Accept (I will check)', 'Aceptar (voy a comprobar)'],
        'Accepteren (ik kom eraan)': ['Accept (on my way)', 'Aceptar (voy enseguida)'],
        'Contant ontvangen & tafel vrijmaken': ['Cash received & free up table', 'Efectivo recibido y liberar mesa'],
        'Betaling gecontroleerd & tafel vrijmaken': ['Payment checked & free up table', 'Pago comprobado y liberar mesa'],
        'Betaald & vrijmaken': ['Paid & free up', 'Pagado y liberar'],
        'Vertrokken zonder betalen': ['Left without paying', 'Se fue sin pagar'],
        'Afgehandeld': ['Handled', 'Atendido'],
        'Roept om hulp': ['Calling for help', 'Pide ayuda'],
        'Heeft een vraag': ['Has a question', 'Tiene una pregunta'],
        'Vraagt om een serveerster': ['Asks for a waiter', 'Pide un camarero'],
        'Rekening tot nu toe': ['Bill so far', 'Cuenta hasta ahora'],
        'Tijd is om': ['Time is up', 'Tiempo agotado'],
        'Dineren tot': ['Dining until', 'Cenando hasta las'],
        'Nog niets besteld': ['Nothing ordered yet', 'Aún no ha pedido nada'],
        'Geaccepteerd door': ['Accepted by', 'Aceptado por'],
        'Bereid door': ['Prepared by', 'Preparado por'],
        'Onderweg met': ['On its way with', 'En camino con'],
        'Bezorgd door': ['Delivered by', 'Servido por'],
        'zojuist': ['just now', 'ahora mismo'],
        'Er zijn op dit moment geen tafels in gebruik': ['There are no tables in use at the moment', 'Ahora mismo no hay mesas en uso'],
        'Geen gasten die om hulp vragen op dit moment': ['No guests asking for help at the moment', 'Ahora mismo ningún cliente pide ayuda'],
        'Geen openstaande betaalverzoeken op dit moment': ['No open payment requests at the moment', 'Ahora mismo no hay solicitudes de pago pendientes'],
        'Gezelschap van': ['Party of', 'Grupo de'],
        'Todo listo! Geen orders in deze categorie': ['Todo listo! No orders in this category', 'Todo listo! No hay pedidos en esta categoría'],
        'Geen bestellingen gevonden in deze categorie': ['No orders found in this category', 'No se han encontrado pedidos en esta categoría'],
        'Geen actieve bestellingen': ['No active orders', 'No hay pedidos activos'],
        'Geen bestellingen in deze categorie': ['No orders in this category', 'No hay pedidos en esta categoría'],
                'Geen betaalverzoeken op dit moment': ['No payment requests at the moment', 'Ahora mismo no hay solicitudes de pago'],
        'Accepteren is niet gelukt': ['Accepting failed', 'No se ha podido aceptar'],
        'Afhandelen is niet gelukt': ['Handling failed', 'No se ha podido atender'],
        'Fout bij accepteren betaalverzoek': ['Error accepting the payment request', 'Error al aceptar la solicitud de pago'],
        'Fout bij vrijmaken tafel': ['Error freeing up the table', 'Error al liberar la mesa'],

        // ---------- Voorraad ----------
        'Las Tapas - Voorraadbeheer': ['Las Tapas - Stock management', 'Las Tapas - Gestión de existencias'],
        'Overzicht en beheer van ingrediënten en drankjes': ['Overview and management of ingredients and drinks', 'Resumen y gestión de ingredientes y bebidas'],
        'Actuele Voorraad': ['Current stock', 'Existencias actuales'],
        'Hier kun je zien hoeveel er nog op voorraad is per gerecht of product. Bij 0 stuks is het uitverkocht, tot en met 5 stuks kleurt het rood': ['Here you can see how much is left in stock per dish or product. At 0 it is sold out, up to 5 it turns red', 'Aquí puedes ver cuánto queda de cada plato o producto. Con 0 está agotado; hasta 5 se marca en rojo'],
        'Product / Gerecht': ['Product / Dish', 'Producto / Plato'],
        'Aantal op voorraad': ['Quantity in stock', 'Cantidad en existencias'],
        'Actie': ['Action', 'Acción'],
        'Acties': ['Actions', 'Acciones'],
        'Voorraad laden': ['Loading stock', 'Cargando existencias'],
        'Geen voorraadgegevens gevonden': ['No stock data found', 'No se han encontrado datos de existencias'],
        'Fout bij laden van voorraadgegevens': ['Error loading stock data', 'Error al cargar las existencias'],
        'Kon voorraad niet bijwerken': ['Could not update stock', 'No se han podido actualizar las existencias'],
        'Uitverkocht (0)': ['Sold out (0)', 'Agotado (0)'],

        // ---------- Inloggen ----------
        'Las Tapas - Medewerker Login': ['Las Tapas - Staff login', 'Las Tapas - Acceso del personal'],
        'Taberna española': ['Spanish taberna', 'Taberna española'],
        'Inloggen voor personeel': ['Staff login', 'Acceso para el personal'],
        'Gebruikersnaam': ['Username', 'Usuario'],
        'Wachtwoord': ['Password', 'Contraseña'],
        'Inloggen': ['Log in', 'Entrar'],
        'De database is niet bereikbaar. Staat WampServer aan': ['The database cannot be reached. Is WampServer running', 'No se puede acceder a la base de datos. ¿Está WampServer en marcha'],
        'Te veel foute pogingen. Probeer het over 15 minuten opnieuw': ['Too many failed attempts. Please try again in 15 minutes', 'Demasiados intentos fallidos. Inténtalo de nuevo dentro de 15 minutos'],

        // ---------- Beheer: algemeen ----------
        'Las Tapas - Beheer': ['Las Tapas - Management', 'Las Tapas - Administración'],
        'Medewerkers': ['Staff', 'Personal'],
        'Menu & prijzen': ['Menu & prices', 'Carta y precios'],
        'Statistieken': ['Statistics', 'Estadísticas'],
        'QR-codes': ['QR codes', 'Códigos QR'],
        'Alle tafels': ['All tables', 'Todas las mesas'],
        '1e etage': ['1st floor', '1ª planta'],
        '2e etage': ['2nd floor', '2ª planta'],
        'Groot tonen': ['Show large', 'Mostrar en grande'],
        'Tafel nr': ['Table no', 'Mesa nº'],
        'Tik op een QR-code om hem schermvullend te tonen. "Printen" print de tafels die je nu ziet (alle, of één etage)': ['Tap a QR code to show it full screen. "Print" prints the tables you see now (all, or one floor)', 'Pulsa un código QR para verlo a pantalla completa. "Imprimir" imprime las mesas que ves ahora (todas o una planta)'],
        'Scan om in te loggen': ['Scan to log in', 'Escanea para entrar'],
        'Hang deze QR-code op een plek waar alleen personeel komt, bijvoorbeeld in de keuken': ['Put this QR code somewhere only staff can see it, for example in the kitchen', 'Coloca este código QR donde solo lo vea el personal, por ejemplo en la cocina'],
        'Serveerster': ['Waiter', 'Camarero/a'],
        'Opgeslagen': ['Saved', 'Guardado'],
        'Toegevoegd': ['Added', 'Añadido'],
        'Verwijderd': ['Deleted', 'Eliminado'],

        // ---------- Beheer: medewerkers ----------
        'Medewerkers Overzicht': ['Staff overview', 'Resumen del personal'],
        'ID': ['ID', 'ID'],
        'Rol': ['Role', 'Rol'],
        'Jijzelf': ['You', 'Tú'],
        'Nieuwe medewerker toevoegen': ['Add new staff member', 'Añadir nuevo empleado'],
        'Medewerker aanpassen': ['Edit staff member', 'Editar empleado'],
        'Volledige naam': ['Full name', 'Nombre completo'],
        'Gebruikersnaam (voor inlog)': ['Username (for login)', 'Usuario (para entrar)'],
        'Nieuw wachtwoord': ['New password', 'Nueva contraseña'],
        'leeg laten om niet te wijzigen': ['leave empty to keep it', 'déjalo vacío para no cambiarla'],
        'Chef (Keuken)': ['Chef (Kitchen)', 'Chef (Cocina)'],
        'Serveerster (Bediening)': ['Waiter (Service)', 'Camarero/a (Sala)'],
        'Baas (Eigenaar / Beheer)': ['Boss (Owner / Admin)', 'Jefe (Propietario / Administración)'],
        'Medewerker opslaan': ['Save staff member', 'Guardar empleado'],
        'Wijzigingen opslaan': ['Save changes', 'Guardar cambios'],
        'Medewerker toegevoegd': ['Staff member added', 'Empleado añadido'],
        'Wijzigingen opgeslagen': ['Changes saved', 'Cambios guardados'],
        'Medewerker verwijderd': ['Staff member deleted', 'Empleado eliminado'],
        'Vul een naam en gebruikersnaam in': ['Enter a name and username', 'Introduce un nombre y un usuario'],
        'Kies een wachtwoord van minimaal 4 tekens': ['Choose a password of at least 4 characters', 'Elige una contraseña de al menos 4 caracteres'],
        'Bijv. Carlos de Kok': ['E.g. Carlos the Cook', 'Ej. Carlos el Cocinero'],
        'Bijv. carlos': ['E.g. carlos', 'Ej. carlos'],

        // ---------- Beheer: menu & prijzen ----------
        'Menu': ['Menu', 'Carta'],
        'Nieuw gerecht': ['New dish', 'Nuevo plato'],
        'Instellingen': ['Settings', 'Ajustes'],
        'Kies een gerecht': ['Choose a dish', 'Elige un plato'],
        'Kies een arrangement': ['Choose a package', 'Elige un menú'],
        '(nog geen gerechten in deze categorie)': ['(no dishes in this category yet)', '(aún no hay platos en esta categoría)'],
        'nog geen gerechten in deze categorie': ['no dishes in this category yet', 'aún no hay platos en esta categoría'],
        'Prijs (€)': ['Price (€)', 'Precio (€)'],
        'betalen gasten met een arrangement zonder drank': ['paid by guests with a package without drinks', 'lo pagan los clientes con un menú sin bebidas'],
        'lager = hoger in de lijst': ['lower = higher in the list', 'menor = más arriba en la lista'],
        'Zichtbaar op het menu': ['Visible on the menu', 'Visible en la carta'],
        'Toevoegen aan menu': ['Add to menu', 'Añadir a la carta'],
        'Wijzigingen zijn direct zichtbaar voor klanten. Is iets tijdelijk op? Haal dan het vinkje "Zichtbaar op het menu" weg': ['Changes are visible to guests immediately. Is something temporarily unavailable? Untick "Visible on the menu"', 'Los cambios se ven al instante. ¿Algo está agotado temporalmente? Desmarca "Visible en la carta"'],
        'Vul de gegevens in en kies een categorie. Een prijs vul je alleen in bij drankjes. Na het toevoegen staat het gerecht meteen op het menu (en krijgt het een voorraad van 25)': ['Fill in the details and choose a category. Only drinks have a price. Once added, the dish appears on the menu straight away (with a stock of 25)', 'Rellena los datos y elige una categoría. Solo las bebidas tienen precio. Al añadirlo, el plato aparece enseguida en la carta (con 25 unidades en existencias)'],
        'Duur (minuten)': ['Duration (minutes)', 'Duración (minutos)'],
        'Prijs volwassene (€)': ['Adult price (€)', 'Precio adulto (€)'],
        'Prijs senior 65+ (€)': ['Senior 65+ price (€)', 'Precio mayores 65+ (€)'],
        'Prijs kind (€)': ['Child price (€)', 'Precio niño (€)'],
        'Drankjes inbegrepen': ['Drinks included', 'Bebidas incluidas'],
        'Nieuw arrangement': ['New package', 'Nuevo menú'],
        'Nieuw arrangement toevoegen': ['Add new package', 'Añadir nuevo menú'],
        'Arrangement toevoegen': ['Add package', 'Añadir menú'],
        'Bijv. Lunch Tapas (1u)': ['E.g. Lunch Tapas (1h)', 'Ej. Tapas de mediodía (1h)'],
        'De naam van een bestaand arrangement kan niet worden gewijzigd, omdat tafels ernaar verwijzen': ['The name of an existing package cannot be changed, because tables refer to it', 'El nombre de un menú existente no se puede cambiar porque las mesas lo usan'],
        'Arrangement definitief verwijderen': ['Delete package permanently', '¿Eliminar el menú definitivamente'],
        'Aantal minuten per keer': ['Minutes per extension', 'Minutos por ampliación'],
        'Prijs per persoon (€)': ['Price per person (€)', 'Precio por persona (€)'],
        'Ronde-limiet': ['Round limit', 'Límite por ronda'],
        'Max. gerechten per persoon': ['Max. dishes per person', 'Máx. platos por persona'],
        '0 = geen limiet': ['0 = no limit', '0 = sin límite'],
        'Duur van een ronde (minuten)': ['Round length (minutes)', 'Duración de una ronda (minutos)'],
        'Drankjes tellen niet mee voor de ronde-limiet': ['Drinks do not count towards the round limit', 'Las bebidas no cuentan para el límite por ronda'],
        'Achtergelaten tafels': ['Abandoned tables', 'Mesas abandonadas'],
        'Automatisch opruimen na ... uur zonder activiteit': ['Clean up automatically after ... hours without activity', 'Cerrar automáticamente tras ... horas sin actividad'],
        'Instellingen opslaan': ['Save settings', 'Guardar ajustes'],

        // ---------- Beheer: statistieken ----------
        'Vandaag': ['Today', 'Hoy'],
        'Afgelopen 7 dagen': ['Last 7 days', 'Últimos 7 días'],
        'Afgelopen 30 dagen': ['Last 30 days', 'Últimos 30 días'],
        'Alles': ['All', 'Todo'],
        'Omzet': ['Revenue', 'Facturación'],
        'Betaalde tafels': ['Paid tables', 'Mesas pagadas'],
        'Gemiddeld per tafel': ['Average per table', 'Media por mesa'],
        'Gemiddeld per gast': ['Average per guest', 'Media por cliente'],
        'Losse drankjes': ['Separate drinks', 'Bebidas aparte'],
        'Tijd verlengd': ['Time extended', 'Tiempo ampliado'],
        'Omzet per dag': ['Revenue per day', 'Facturación por día'],
        'Per arrangement': ['Per package', 'Por menú'],
        'Per betaalmethode': ['Per payment method', 'Por forma de pago'],
        'Populairste gerechten': ['Most popular dishes', 'Platos más populares'],
        'Populairste drankjes': ['Most popular drinks', 'Bebidas más populares'],
        'Drukste uren': ['Busiest hours', 'Horas de más actividad'],
        'Aantal bestelde gerechten en drankjes per uur': ['Number of dishes and drinks ordered per hour', 'Número de platos y bebidas pedidos por hora'],
        'Nog geen gegevens in deze periode': ['No data in this period yet', 'Aún no hay datos en este periodo'],
        'Beoordelingen van gasten': ['Guest reviews', 'Valoraciones de clientes'],
        'Nog geen beoordelingen in deze periode. Gasten kunnen een beoordeling geven nadat ze hebben betaald': ['No reviews in this period yet. Guests can leave a review after paying', 'Aún no hay valoraciones en este periodo. Los clientes pueden valorar después de pagar'],
        'Laatst afgesloten tafels': ['Recently closed tables', 'Últimas mesas cerradas'],
        'Nog geen afgesloten tafels in deze periode. Tafels komen hier zodra een serveerster ze vrijmaakt': ['No closed tables in this period yet. Tables appear here once a waiter frees them up', 'Aún no hay mesas cerradas en este periodo. Aparecen aquí cuando un camarero las libera'],
        'Afgerond': ['Closed', 'Cerrada'],
        'Niet betaald': ['Not paid', 'No pagado'],
        'Contant': ['Cash', 'Efectivo'],
        'Online': ['Online', 'Online'],
        'Automatisch opgeruimd': ['Cleaned up automatically', 'Cerrada automáticamente'],
        'Personeel': ['Staff', 'Personal'],

        // ---------- Beheer: QR-codes ----------
        'QR-codes voor de tafels': ['QR codes for the tables', 'Códigos QR de las mesas'],
        'Elke QR-code bevat een geheime code voor die tafel': ['Each QR code contains a secret code for that table', 'Cada código QR contiene un código secreto para esa mesa'],
        'Oude QR-codes (zonder code) werken niet meer': ['Old QR codes (without a code) no longer work', 'Los códigos QR antiguos (sin código) ya no funcionan'],
        'print deze nieuwe versie en vervang de oude op de tafels. De QR-code voor medewerkers staat op een aparte pagina': ['print this new version and replace the old ones on the tables. The QR code for staff is on a separate page', 'imprime esta nueva versión y sustituye los antiguos en las mesas. El código QR del personal está en otra página'],
        'QR-code medewerkers': ['Staff QR code', 'Código QR del personal'],
        'Adres van de klantpagina': ['Address of the guest page', 'Dirección de la página de clientes'],
        'QR-codes maken': ['Create QR codes', 'Crear códigos QR'],
        'Printen': ['Print', 'Imprimir'],
        'Scan om te bestellen': ['Scan to order', 'Escanea para pedir'],
        'Kopieer link': ['Copy link', 'Copiar enlace'],
        'Gekopieerd': ['Copied', 'Copiado'],
        'Kopieer deze link': ['Copy this link', 'Copia este enlace'],
        'Inloggen personeel': ['Staff login', 'Acceso del personal'],
        'Alleen voor medewerkers': ['Staff only', 'Solo para el personal'],
        'Scan met je telefoon of tablet om in te loggen als chef, serveerster of beheerder': ['Scan with your phone or tablet to log in as chef, waiter or manager', 'Escanea con tu móvil o tableta para entrar como chef, camarero o administrador'],
        'Print QR-code': ['Print QR code', 'Imprimir código QR'],
    };

    // ======================= PATRONEN (teksten met getallen of namen) =======================
    // {1}, {2}, ... worden overgenomen (en zo mogelijk zelf ook vertaald).
    const P = [
        ['Tafel {1}', 'Table {1}', 'Mesa {1}'],
        ['Tafel {1}: {2}', 'Table {1}: {2}', 'Mesa {1}: {2}'],
        ['Mesa {1} (Tafel)', 'Mesa {1} (Table)', 'Mesa {1}'],
        ['Tus Pedidos · Tafel', 'Tus Pedidos · Table', 'Tus Pedidos · Mesa'],
        ['Bezet ({1})', 'Occupied ({1})', 'Ocupada ({1})'],
        ['{1} items gekozen', '{1} items chosen', '{1} artículos elegidos'],
        ['{1}x geselecteerd', '{1}x selected', '{1}x seleccionado'],
        ['Nog maar {1} over', 'Only {1} left', 'Solo quedan {1}'],
        ['Let op: Nog maar {1} over in voorraad', 'Note: only {1} left in stock', 'Atención: solo quedan {1} en existencias'],
        ['Besteld om: {1}', 'Ordered at: {1}', 'Pedido a las: {1}'],
        ['Tijd: {1}', 'Time: {1}', 'Hora: {1}'],
        ['{1}e etage · Tafel {2}', 'Floor {1} · Table {2}', 'Planta {1} · Mesa {2}'],
        ['\'{1}\' staat niet op het menu', '\'{1}\' is not on the menu', '\'{1}\' no está en la carta'],
        ['Bon gemaild naar {1}', 'Receipt emailed to {1}', 'Recibo enviado a {1}'],
        ['Bon kon niet worden gemaild: {1}', 'Receipt could not be emailed: {1}', 'No se ha podido enviar el recibo: {1}'],
        ['Tafel {1} is al in gebruik door', 'Table {1} is already in use by', 'La mesa {1} ya está ocupada por'],
        ['({1} personen, {2})', '({1} people, {2})', '({1} personas, {2})'],
        ['({1} persoon, {2})', '({1} person, {2})', '({1} persona, {2})'],
        ['Status: {1}', 'Status: {1}', 'Estado: {1}'],
        ['{1} · niet inbegrepen', '{1} · not included', '{1} · no incluido'],
        ['{1} minuten', '{1} minutes', '{1} minutos'],
        ['Jullie {1} uur beginnen bij de eerste bestelling', 'Your {1} hours start with the first order', 'Vuestras {1} horas empiezan con el primer pedido'],
        ['kost {1} per persoon. Voor jullie {2} personen is dat', 'costs {1} per person. For your party of {2} that is', 'cuesta {1} por persona. Para vosotros {2} son'],
        ['kost {1} per persoon. Voor jullie {2} persoon is dat', 'costs {1} per person. For you that is', 'cuesta {1} por persona. Para ti son'],
        ['Verlengen met {1} min ({2})', 'Extend by {1} min ({2})', 'Ampliar {1} min ({2})'],
        ['Verlengd tot {1}', 'Extended until {1}', 'Ampliado hasta las {1}'],
        ['van {1} gerechten ({2} per persoon per {3} min). Drankjes tellen niet mee', 'of {1} dishes left ({2} per person per {3} min). Drinks do not count', 'de {1} platos ({2} por persona cada {3} min). Las bebidas no cuentan'],
        ['Ronde vol: max. {1} gerechten per {2} min', 'Round full: max. {1} dishes per {2} min', 'Ronda completa: máx. {1} platos cada {2} min'],
        ['Per ronde van {1} minuten kunnen jullie {2} gerechten bestellen ({3} per persoon). Vanaf {4} kan het weer. Drankjes kunnen altijd', 'Per round of {1} minutes you can order {2} dishes ({3} per person). You can order again from {4}. Drinks are always possible', 'En cada ronda de {1} minutos podéis pedir {2} platos ({3} por persona). Podréis volver a pedir a partir de las {4}. Las bebidas se pueden pedir siempre'],
        ['Per ronde van {1} minuten kunnen jullie {2} gerechten bestellen ({3} per persoon). Drankjes kunnen altijd', 'Per round of {1} minutes you can order {2} dishes ({3} per person). Drinks are always possible', 'En cada ronda de {1} minutos podéis pedir {2} platos ({3} por persona). Las bebidas se pueden pedir siempre'],
        ['Per ronde van {1} minuten kunnen jullie {2} gerechten bestellen ({3} per persoon). Deze ronde nog {4}. De volgende ronde begint om {5}', 'Per round of {1} minutes you can order {2} dishes ({3} per person). {4} left this round. The next round starts at {5}', 'En cada ronda de {1} minutos podéis pedir {2} platos ({3} por persona). Quedan {4} en esta ronda. La siguiente empieza a las {5}'],
        ['Per ronde van {1} minuten kunnen jullie {2} gerechten bestellen ({3} per persoon). Deze ronde nog {4}', 'Per round of {1} minutes you can order {2} dishes ({3} per person). {4} left this round', 'En cada ronda de {1} minutos podéis pedir {2} platos ({3} por persona). Quedan {4} en esta ronda'],
        ['Je hebt al het maximum aantal ({1}) van dit item in je mandje gezet', 'You already have the maximum number ({1}) of this item in your basket', 'Ya tienes el máximo ({1}) de este artículo en tu pedido'],
        ['Helaas, van \'{1}\' zijn er nog maar {2} op voorraad (gevraagd: {3})', 'Sorry, only {2} of \'{1}\' left in stock (requested: {3})', 'Lo sentimos, de \'{1}\' solo quedan {2} (pedidos: {3})'],
        ['\'{1}\' staat niet (meer) op het menu', '\'{1}\' is not (or no longer) on the menu', '\'{1}\' no está (ya) en la carta'],
        ['heeft je oproep geaccepteerd en komt naar tafel {1}', 'has accepted your call and is coming to table {1}', 'ha aceptado tu llamada y va a la mesa {1}'],
        ['Een serveerster heeft je oproep geaccepteerd en komt naar tafel {1}', 'A waiter has accepted your call and is coming to table {1}', 'Un camarero ha aceptado tu llamada y va a la mesa {1}'],
        ['komt naar tafel {1} om jullie online betaling te controleren', 'is coming to table {1} to check your online payment', 'va a la mesa {1} para comprobar vuestro pago online'],
        ['De serveerster komt naar tafel {1} om jullie online betaling te controleren', 'A waiter is coming to table {1} to check your online payment', 'Un camarero va a la mesa {1} para comprobar vuestro pago online'],
        ['heeft jullie verzoek geaccepteerd en is onderweg naar tafel {1} om de betaling in ontvangst te nemen', 'has accepted your request and is on the way to table {1} to collect the payment', 'ha aceptado vuestra solicitud y va de camino a la mesa {1} para cobrar'],
        ['De serveerster heeft jullie verzoek geaccepteerd en is onderweg naar tafel {1} om de betaling in ontvangst te nemen', 'A waiter has accepted your request and is on the way to table {1} to collect the payment', 'Un camarero ha aceptado vuestra solicitud y va de camino a la mesa {1} para cobrar'],
        ['{1} is al onderweg naar jullie tafel', '{1} is already on the way to your table', '{1} ya va de camino a vuestra mesa'],
        ['Deze bestelling is al geaccepteerd door {1}', 'This order has already been accepted by {1}', 'Este pedido ya lo ha aceptado {1}'],
        ['Dit betaalverzoek is al geaccepteerd door {1}', 'This payment request has already been accepted by {1}', 'Esta solicitud de pago ya la ha aceptado {1}'],
        ['Deze oproep is al geaccepteerd door {1}', 'This call has already been accepted by {1}', 'Esta llamada ya la ha aceptado {1}'],
        ['(was {1})', '(was {1})', '(era a las {1})'],
        ['Laatste activiteit: {1}', 'Last activity: {1}', 'Última actividad: {1}'],
        ['{1} min geleden', '{1} min ago', 'hace {1} min'],
        ['Geroepen om {1} ({2})', 'Called at {1} ({2})', 'Llamó a las {1} ({2})'],
        ['Tafel {1} vrijmaken omdat de gasten zijn vertrokken ZONDER te betalen{2}? Dit wordt apart bijgehouden in de statistieken', 'Free up table {1} because the guests left WITHOUT paying{2}? This is tracked separately in the statistics', '¿Liberar la mesa {1} porque los clientes se fueron SIN pagar{2}? Se registra aparte en las estadísticas'],
        ['Bevestig: heb je gecontroleerd dat de online betaling van Tafel {1} echt gelukt is? De tafel komt daarna weer vrij', 'Confirm: have you checked that the online payment for table {1} really went through? The table will then be freed up', 'Confirma: ¿has comprobado que el pago online de la mesa {1} se ha realizado? Después la mesa quedará libre'],
        ['Bevestig: is de rekening van Tafel {1} betaald? De tafel komt daarna weer vrij', 'Confirm: has the bill for table {1} been paid? The table will then be freed up', 'Confirma: ¿se ha pagado la cuenta de la mesa {1}? Después la mesa quedará libre'],
        ['Bijna op ({1})', 'Almost out ({1})', 'Casi agotado ({1})'],
        ['Voldoende ({1})', 'Enough ({1})', 'Suficiente ({1})'],
        ['Open tafel {1}', 'Open table {1}', 'Abrir mesa {1}'],
        ['{1} (verborgen)', '{1} (hidden)', '{1} (oculto)'],
        ['{1} ({2} uur)', '{1} ({2} hours)', '{1} ({2} horas)'],
        ['{1} ({2} min)', '{1} ({2} min)', '{1} ({2} min)'],
        ['Volw: {1} | 65+: {2} | Kind: {3}', 'Adult: {1} | 65+: {2} | Child: {3}', 'Adulto: {1} | 65+: {2} | Niño: {3}'],
        ['{1}× Volwassene', '{1}× Adult', '{1}× Adulto'],
        ['{1}× Senior (65+)', '{1}× Senior (65+)', '{1}× Mayor (65+)'],
        ['{1}× Kind', '{1}× Child', '{1}× Niño'],
        ['Verlenging {1} min ({2} pers.)', 'Extension {1} min ({2} pers.)', 'Ampliación {1} min ({2} pers.)'],
        ['Te betalen: {1}', 'To pay: {1}', 'A pagar: {1}'],
        ['{1} volw, {2} sen, {3} kind', '{1} adults, {2} seniors, {3} children', '{1} adultos, {2} mayores, {3} niños'],
        ['{1} ({2} tafels, {3} gasten)', '{1} ({2} tables, {3} guests)', '{1} ({2} mesas, {3} clientes)'],
        ['Contant ({1} tafels)', 'Cash ({1} tables)', 'Efectivo ({1} mesas)'],
        ['Online ({1} tafels)', 'Online ({1} tables)', 'Online ({1} mesas)'],
        ['Onbekend ({1} tafels)', 'Unknown ({1} tables)', 'Desconocido ({1} mesas)'],
        ['Vertrokken zonder betalen ({1})', 'Left without paying ({1})', 'Se fueron sin pagar ({1})'],
        ['Gemiddelde beoordeling ({1})', 'Average rating ({1})', 'Valoración media ({1})'],
        ['Betaald ({1})', 'Paid ({1})', 'Pagado ({1})'],
        ['tafel {1}', 'table {1}', 'mesa {1}'],
        ['Welkom, {1}', 'Welcome, {1}', 'Bienvenido/a, {1}'],
        ['{1} sterren', '{1} stars', '{1} estrellas'],
        ['Medewerker aanpassen: {1}', 'Edit staff member: {1}', 'Editar empleado: {1}'],
        ['Weet je zeker dat je {1} wilt verwijderen', 'Are you sure you want to delete {1}', '¿Seguro que quieres eliminar a {1}'],
        ['{1} definitief verwijderen? Tip: haal liever het vinkje Zichtbaar weg als het tijdelijk op is', 'Delete {1} permanently? Tip: untick Visible instead if it is only temporarily unavailable', '¿Eliminar {1} definitivamente? Consejo: si solo está agotado temporalmente, desmarca Visible'],
        ['De gebruikersnaam \'{1}\' is al in gebruik', 'The username \'{1}\' is already taken', 'El usuario \'{1}\' ya está en uso'],
        ['Onjuiste gebruikersnaam of wachtwoord. Nog {1} pogingen', 'Incorrect username or password. {1} attempts left', 'Usuario o contraseña incorrectos. Te quedan {1} intentos'],
        ['Onjuiste gebruikersnaam of wachtwoord. Nog {1} poging', 'Incorrect username or password. {1} attempt left', 'Usuario o contraseña incorrectos. Te queda {1} intento'],
        ['Te veel foute pogingen. Probeer het na {1} opnieuw', 'Too many failed attempts. Please try again after {1}', 'Demasiados intentos fallidos. Inténtalo de nuevo después de las {1}'],
        ['Voorbeeld (tafel 1): {1}', 'Example (table 1): {1}', 'Ejemplo (mesa 1): {1}'],
    ];

    // ======================= VERTAALLOGICA =======================
    let taal = 'nl';
    try { taal = localStorage.getItem('taal') || 'nl'; } catch (e) {}
    if (!TALEN[taal]) taal = 'nl';
    const index = { en: 0, es: 1 };

    const patronen = P.map(([nl, en, es]) => {
        const delen = nl.split(/(\{\d\})/);
        const volgorde = [];
        const bron = delen.map(d => {
            const m = d.match(/^\{(\d)\}$/);
            if (m) { volgorde.push(Number(m[1])); return '(.+?)'; }
            return d.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }).join('');
        const vasteTekst = nl.replace(/\{\d\}/g, '').length;
        return { re: new RegExp('^' + bron + '$', 's'), volgorde, en, es, vasteTekst };
    }).sort((a, b) => b.vasteTekst - a.vasteTekst);   // specifieke patronen eerst

    function vertaalKern(kern, diepte) {
        if (!kern) return undefined;
        if (Object.prototype.hasOwnProperty.call(W, kern)) return W[kern][index[taal]];
        if (diepte > 2) return undefined;
        for (const p of patronen) {
            const m = kern.match(p.re);
            if (!m) continue;
            let uit = p[taal];
            p.volgorde.forEach((nr, i) => {
                const waarde = m[i + 1];
                const vertaald = vertaalTekst(waarde, diepte + 1);
                uit = uit.split('{' + nr + '}').join(vertaald !== undefined ? vertaald : waarde);
            });
            return uit;
        }
        return undefined;
    }

    // Probeert de tekst zelf, en daarna zonder emoji's/leestekens aan het begin en eind
    function vertaalTekst(tekst, diepte = 0) {
        if (taal === 'nl' || !tekst || !/\p{L}/u.test(tekst)) return undefined;
        const pogingen = [
            /^(\s*)([\s\S]*?)(\s*)$/u,
            /^([^\p{L}\p{N}(]*)([\s\S]*?)([^\p{L}\p{N})]*)$/u,
            /^([^\p{L}\p{N}]*)([\s\S]*?)([^\p{L}\p{N}]*)$/u,
        ];
        for (const re of pogingen) {
            const m = tekst.match(re);
            if (!m || !m[2]) continue;
            const vertaald = vertaalKern(m[2], diepte);
            if (vertaald !== undefined) return m[1] + vertaald + m[3];
        }
        return undefined;
    }

    function vertaal(tekst) {
        const v = vertaalTekst(String(tekst));
        return v !== undefined ? v : tekst;
    }
    window.vertaal = vertaal;

    // ---- Tekstnodes ----
    const OVERSLAAN = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'NOSCRIPT', 'CODE']);

    function doeTekstNode(node) {
        const ouder = node.parentNode;
        if (!ouder || OVERSLAAN.has(ouder.nodeName) || (ouder.closest && ouder.closest('[data-geen-vertaling]'))) return;
        // Heeft de pagina zelf de tekst veranderd? Dan is dat de nieuwe (Nederlandse) bron
        if (node.__nl === undefined || node.nodeValue !== node.__uit) node.__nl = node.nodeValue;
        const vertaald = taal === 'nl' ? undefined : vertaalTekst(node.__nl);
        const nieuw = vertaald !== undefined ? vertaald : node.__nl;
        node.__uit = nieuw;
        if (node.nodeValue !== nieuw) node.nodeValue = nieuw;
    }

    const ATTRIBUTEN = ['placeholder', 'title', 'aria-label', 'label'];
    function doeElement(el) {
        if (el.closest && el.closest('[data-geen-vertaling]')) return;
        ATTRIBUTEN.forEach(a => {
            if (!el.hasAttribute(a)) return;
            const sleutel = 'nl' + a.replace(/-./g, x => x[1].toUpperCase());
            const huidig = el.getAttribute(a);
            if (el.dataset[sleutel] === undefined || huidig !== el.__uitAttr?.[a]) el.dataset[sleutel] = huidig;
            const bron = el.dataset[sleutel];
            const vertaald = taal === 'nl' ? undefined : vertaalTekst(bron);
            const nieuw = vertaald !== undefined ? vertaald : bron;
            el.__uitAttr = Object.assign(el.__uitAttr || {}, { [a]: nieuw });
            if (huidig !== nieuw) el.setAttribute(a, nieuw);
        });
        if (el.tagName === 'INPUT' && (el.type === 'submit' || el.type === 'button')) {
            if (el.dataset.nlValue === undefined) el.dataset.nlValue = el.value;
            const v = taal === 'nl' ? undefined : vertaalTekst(el.dataset.nlValue);
            el.value = v !== undefined ? v : el.dataset.nlValue;
        }
    }

    let bezig = false;
    function vertaalBoom(wortel) {
        if (!wortel) return;
        bezig = true;
        if (wortel.nodeType === Node.TEXT_NODE) {
            doeTekstNode(wortel);
        } else if (wortel.nodeType === Node.ELEMENT_NODE) {
            if (OVERSLAAN.has(wortel.nodeName)) { bezig = false; return; }
            doeElement(wortel);
            wortel.querySelectorAll('*').forEach(doeElement);
            const walker = document.createTreeWalker(wortel, NodeFilter.SHOW_TEXT);
            let n;
            while ((n = walker.nextNode())) doeTekstNode(n);
        }
        bezig = false;
    }

    let nlTitel = null;
    function vertaalTitel() {
        if (nlTitel === null || document.title !== vertaalTitel.__uit) nlTitel = document.title;
        const v = taal === 'nl' ? undefined : vertaalTekst(nlTitel);
        document.title = v !== undefined ? v : nlTitel;
        vertaalTitel.__uit = document.title;
    }

    // Nieuwe of veranderde inhoud meteen vertalen
    const observer = new MutationObserver(mutaties => {
        if (bezig) return;
        for (const m of mutaties) {
            if (m.type === 'childList') {
                m.addedNodes.forEach(vertaalBoom);
            } else if (m.type === 'characterData') {
                if (m.target.nodeValue !== m.target.__uit) vertaalBoom(m.target);
            } else if (m.type === 'attributes') {
                const el = m.target;
                if (el.getAttribute(m.attributeName) !== el.__uitAttr?.[m.attributeName]) doeElement(el);
            }
        }
    });

    // Pop-upmeldingen ook vertalen
    const origAlert = window.alert.bind(window);
    const origConfirm = window.confirm.bind(window);
    const origPrompt = window.prompt.bind(window);
    window.alert = msg => origAlert(vertaal(msg));
    window.confirm = msg => origConfirm(vertaal(msg));
    window.prompt = (msg, standaard) => origPrompt(vertaal(msg), standaard);

    // ======================= TAALKNOP =======================
    function maakKiezer() {
        const plek = document.getElementById('taal-plek');
        const kiezer = document.createElement('div');
        kiezer.className = 'taal-kiezer' + (plek ? '' : ' zwevend');
        kiezer.setAttribute('data-geen-vertaling', '');
        kiezer.innerHTML = `
            <button type="button" class="taal-knop" aria-label="Taal / Language / Idioma" aria-haspopup="true">
                <span class="taal-vlag">${TALEN[taal].vlag}</span> <span class="taal-code">${taal.toUpperCase()}</span> ▾
            </button>
            <div class="taal-menu" hidden>
                ${Object.entries(TALEN).map(([code, t]) =>
                    `<button type="button" data-taal="${code}" class="${code === taal ? 'gekozen' : ''}">${t.vlag} ${t.naam}</button>`).join('')}
            </div>`;
        (plek || document.body).appendChild(kiezer);

        const knop = kiezer.querySelector('.taal-knop');
        const menu = kiezer.querySelector('.taal-menu');
        knop.addEventListener('click', e => {
            e.stopPropagation();
            menu.hidden = !menu.hidden;
            if (!menu.hidden) {
                // Openen naar de kant waar genoeg ruimte is (anders valt het menu buiten beeld)
                const r = knop.getBoundingClientRect();
                const naarRechts = r.left + 170 < window.innerWidth;
                menu.style.left = naarRechts ? '0' : 'auto';
                menu.style.right = naarRechts ? 'auto' : '0';
            }
        });
        document.addEventListener('click', () => { menu.hidden = true; });
        menu.querySelectorAll('button').forEach(b => b.addEventListener('click', () => zetTaal(b.dataset.taal)));
    }

    function zetTaal(nieuw) {
        if (!TALEN[nieuw]) return;
        taal = nieuw;
        try { localStorage.setItem('taal', taal); } catch (e) {}
        document.documentElement.lang = taal;
        document.querySelectorAll('.taal-kiezer').forEach(k => {
            k.querySelector('.taal-vlag').textContent = TALEN[taal].vlag;
            k.querySelector('.taal-code').textContent = taal.toUpperCase();
            k.querySelectorAll('.taal-menu button').forEach(b => b.classList.toggle('gekozen', b.dataset.taal === taal));
            k.querySelector('.taal-menu').hidden = true;
        });
        vertaalBoom(document.body);
        vertaalTitel();
    }
    window.zetTaal = zetTaal;

    const stijl = document.createElement('style');
    stijl.textContent = `
        .taal-kiezer { position: relative; display: inline-block; font-family: 'Segoe UI', sans-serif; }
        .taal-kiezer.zwevend { position: fixed; top: 10px; right: 10px; z-index: 3000; }
        .taal-knop {
            background: rgba(255,255,255,0.92); color: #2b1d19; border: 1px solid rgba(0,0,0,0.15);
            border-radius: 20px; padding: 6px 10px; font-weight: bold; font-size: 0.8rem; cursor: pointer;
            margin: 0; line-height: 1.2; font-family: inherit; white-space: nowrap;
        }
        .taal-knop:hover { background: white; }
        .taal-menu {
            position: absolute; right: 0; top: calc(100% + 6px); background: white; border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2); overflow: hidden; z-index: 3001; min-width: 150px;
        }
        .taal-menu button {
            display: block; width: 100%; text-align: left; background: white; color: #2b1d19; border: none;
            padding: 10px 14px; font-size: 0.9rem; cursor: pointer; margin: 0; border-radius: 0; font-family: inherit; font-weight: normal;
        }
        .taal-menu button:hover { background: #fbf1e6; }
        .taal-menu button.gekozen { font-weight: bold; background: #fff3e6; }
        @media (max-width: 480px) { .taal-code { display: none; } }
        @media print { .taal-kiezer { display: none !important; } }
    `;

    function start() {
        document.head.appendChild(stijl);
        document.documentElement.lang = taal;
        maakKiezer();
        vertaalBoom(document.body);
        vertaalTitel();
        observer.observe(document.body, {
            childList: true, subtree: true, characterData: true,
            attributes: true, attributeFilter: ATTRIBUTEN,
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
