# Notes

## 1. Lavori eseguiti e lavori tagliati

Ho lavorato principalmente sulle prime due parti. Per necessità di temp ho tagliato la terza parte. Ritengo che la prova,
nonostante sia molto completa, risulti complessa per chi non conosce il dominio e le varie sfaccettature, il che richiede
tempo aggiuntivo per comprendere cosa si sta facendo prima di farlo.

## 2. Normalizzazione vendor

I vendor sono normalizzati utilizzando un pattern adapter. Abbiamo un'interfaccia comune che i vari adapter implementano.
I singoli adapter implementano una funzione che restituisce se supportano un determinato vendor attraverso il topic passato,
e una funzione di normalizzazione che restituisce un messaggio normalizzato nominato NormalizedEvent. A questo punto, aggiungere un
vendor vuol dire creare un adapter specifico per quel vendor e aggiungerlo nel builder del Registry che si occupa di risolvere
l'adapter corretto, all'interno dell'IngestionServiceProvider

## 3. Idempotenza e riconciliazione

Innanzitutto ho disaccoppiato messaggi ricevuti e messaggi normalizzati, in modo da non perdere dati in caso di errori nella
normalizzazione. Il primo stadio di idempotenza iniza qua, dove creo una dedup key usando il topic e il payload del messaggio, che
vengono passati ad una funzione di hash. Dopodiché utilizzo questo metodo anche per deduplicare i messaggi processati, in modo
da evitare possibili race conditions e duplicati che potrebbero verificarsi in un ambiente che utilizza job asincroni.

Per riconciliare lo stato, quando viene pubblicato un comando, non viene marcato come inviato, ma viene anche salvato un timestamp
`reconcile_by = now + 10 minutes` (due volte la finestra di reporting di CP3000, tollera un miss o un problema di lettura).
Dopo ogni lettura del quadro viene chiamato `ReconcilePendingCommands::reconcile()`, questo matcha i command che hanno ancora come stato `sent`
e `reconcile_by` futuro e li blocca, controllando lo stato per ogni comando:
- on -> switch_state = On
- off -> switch_state = Off
- dim -> livello dim riportato = richiesto nel comando

## 4. Browser e gestione del flusso di dati

Abbiamo un unico processo supervisionato con un loop interno a 5 secondi. Ogni tick legge solo `device_states`, che veine aggiornato dal
normalizer, non leggiamo `device_readings`. Così facendo produciamo:

- 1 snapshot feelt (conteggi, potenza, allarmi) ogni 5 secondi
- 1 snapshot per cabinet solo quando il drill-down è aperto

Tenendo i canali aperti solo rispetto a ciò che si vede sullo schermo evitiamo di bombardare il browser di eventi.
Il browser vede solo dati già aggregati, già throttati e già filtrati per contesto.

## 5. NON ESEGUITO

## 6. Scalare

Al momento abbiamo un long-lived process che facciamo partire con un comando. Andando live in scala, avrebbe più senso far gestire
questi long-lived processes da sistemi che siano resilienti, come Temporal.io. Per esempio, se parliamo di 200k punti luce, avere
un sistema di ingestione fault-tolerant by design ci permette di concentrare gli sforzi sulla gestione del processo delle letture.

Si potrebbe anche sviluppare il sistema come un servizio event-sourcing, lo stato sarebbe una proiezione di tutti gli eventi, ma
sarebbe stato complesso implementarlo nel tempo a disposizione.

## 7. AI

Ho utilizzato l'AI in ogni fase dello sviluppo, nella fase di analisi per aiutarmi a comprendere meglio il dominio del problema
e certe terminologie. Durante la fase di analisi della soluzione ho utilizzato modelli grandi per cercare falle logiche che
avrebbero potuto manifestarsi nel progetto.
Infine ho usato modelli di sviluppo per aiutarmi a scrivere codice più velocemente, specialmente nella fase frontend, poiché
non sono riuscito a dedicare più di 5 ore a questo progetto, che sicuramente ne avrebbe meritate di più.
