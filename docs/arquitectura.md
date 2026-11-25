# Arquitectura — Sistema Inteligente de Control de Peaje

Todo corre en la nube: **nada depende de `localhost`, IPs privadas ni túneles a una PC personal.**

## Diagrama general

```mermaid
flowchart LR
    subgraph DATOS["Datos y entrenamiento"]
        RF[(Roboflow<br/>datasets M2 · M3 · M4)]
        CO[Google Colab T4<br/>notebooks 01–04]
        RF -- "API key (Secretos de Colab)" --> CO
    end

    subgraph GPU["RunPod · GPU (puerto HTTP 8000)"]
        FA["FastAPI app.py<br/>M1 placas YOLOv9-t ONNX + fast-plate-ocr<br/>M2 YOLO11s-seg vehículos<br/>M3 YOLO11s-pose llantas → ejes<br/>M4 YOLO11s-seg pasajeros (sin rostros)"]
    end
    CO -- "pesos .pt (m2, m3, m4)" --> FA
    COLAB2["Colab (respaldo)<br/>servidor_inferencia_runpod_colab.ipynb<br/>+ túnel Cloudflare"]:::alt

    subgraph WEB["Subdominio HTTPS · VPS / hosting compartido"]
        LV["Laravel 12<br/>Panel Blade · API REST v1<br/>AgentePeajeService"]
        DB[(SQLite / MySQL<br/>usuarios · vehiculos · casetas · tarifas<br/>pasos · alertas · configuraciones · eventos_hardware)]
        LV <--> DB
    end

    G[Google OAuth 2.0<br/>Socialite web · ID Token móvil]
    PU[Pusher Channels<br/>tiempo real]
    LLM[Gemini API<br/>o OpenAI / Groq / Ollama]

    APP["App Flutter (APK)<br/>Modo Caseta 2–5 fps<br/>Modo Consulta"]
    NAV["Navegador<br/>panel en vivo + burbuja chat"]
    ESP["ESP32 DevKit<br/>LED azul/verde/rojo + buzzer"]

    APP -- "google_sign_in → ID Token" --> G
    APP -- "HTTPS Bearer: /auth/google, /pasos/frame, /agente/chat" --> LV
    NAV -- "HTTPS sesión (Google)" --> LV
    NAV -. "WebSocket wss" .- PU
    LV -- "verifica tokeninfo / OAuth" --> G
    LV -- "POST /inferir · /inferir-placa<br/>X-API-Token (INFERENCE_URL)" --> FA
    LV -. "INFERENCE_URL alternativa" .-> COLAB2
    LV -- "broadcast paso.registrado / alerta.creada" --> PU
    LV -- "function calling (tools sobre la BD)" --> LLM
    ESP -- "HTTPS GET /hardware/ultimo-evento<br/>X-Hardware-Token cada 1 s" --> LV

    classDef alt stroke-dasharray: 5 5;
```

## Secuencia de un paso por la caseta

```mermaid
sequenceDiagram
    autonumber
    participant App as App Flutter (Modo Caseta)
    participant L as Laravel (subdominio)
    participant E as ESP32
    participant R as RunPod FastAPI
    participant DB as BD
    participant P as Pusher
    participant W as Panel web

    App->>L: POST /api/v1/pasos/frame (JPEG, caseta, modo=auto) Bearer
    L->>DB: eventos_hardware ← "inferencia"
    E->>L: GET /hardware/ultimo-evento (cada 1 s)
    L-->>E: {tipo_senal: inferencia} → LED azul
    L->>R: POST /inferir (X-API-Token)
    R-->>L: {placa, vehiculo, ejes, keypoints, pasajeros, imagen_anotada_base64, latencia_ms}
    L->>L: normaliza placa · busca vehículo (exacto o equivalente OCR)
    L->>DB: tarifa(categoría, ejes) · descuenta saldo (transacción)
    L->>DB: guarda evidencia anotada · paso · alerta (si aplica)
    L->>P: paso.registrado (+ contadores) · alerta.creada
    P-->>W: actualización en vivo sin recargar
    L->>DB: eventos_hardware ← "ok" | "alerta"
    E->>L: GET /hardware/ultimo-evento
    L-->>E: {tipo_senal: ok} → LED verde + 1 beep  |  {alerta} → LED rojo + 3 beeps
    L-->>App: paso (placa, categoría, ejes, pasajeros, Bs, estado)
```

## Agente conversacional

```mermaid
flowchart TD
    Q[Mensaje y/o imagen] --> IMG{¿Imagen?}
    IMG -- sí --> M1[RunPod /inferir-placa] --> LEG{¿Legible?}
    LEG -- no --> PIDE[“Envía otra foto”]
    LEG -- sí --> PL[Placa normalizada]
    IMG -- no --> EX[Extraer placas del texto]
    PL & EX --> T[Herramientas BD:<br/>consultar_placa · similares · historial<br/>resumen_caseta · alertas · tarifas]
    T --> LLM{¿LLM disponible?}
    LLM -- sí --> G[Gemini function calling<br/>system prompt estricto]
    LLM -- no / error --> D[Redacción determinista]
    G & D --> R[Respuesta + estado 1–4<br/>+ “¿Quisiste decir …?”]
```

| Estado | Condición | Respuesta |
|---|---|---|
| 1 Registrada y con paso | en `vehiculos` y con pasos | fecha/hora, caseta, categoría, ejes, monto |
| 2 Registrada sin paso | en `vehiculos`, sin pasos | datos del vehículo y saldo |
| 3 Identificada no registrada | no en `vehiculos`, sí en `pasos` | **alerta de posible evasión** |
| 4 No existe | en ninguna tabla | + sugerencias Levenshtein ≤ 2 (O↔0, I↔1, B↔8, S↔5, 3↔8) |

## Modelo de datos

```mermaid
erDiagram
    usuarios ||--o{ pasos : registra
    usuarios ||--o{ alertas : atiende
    casetas ||--o{ pasos : ""
    casetas ||--o{ alertas : ""
    casetas ||--o{ eventos_hardware : ""
    vehiculos ||--o{ pasos : ""
    tarifas ||--o{ pasos : aplica
    pasos ||--o{ alertas : genera
    pasos ||--o{ eventos_hardware : señala
    usuarios { string email UK
               enum rol "administrador|operador"
               bool activo }
    vehiculos { string placa UK
                enum categoria
                int ejes
                decimal saldo }
    tarifas { enum categoria
              int ejes "2..6+"
              decimal monto "Bs" }
    pasos { string placa_detectada
            string categoria_cobrada
            int ejes_cobrados
            int pasajeros
            decimal monto
            enum estado
            string evidencia_path }
    alertas { enum tipo
              bool atendida }
    configuraciones { string clave UK
                      text valor "INFERENCE_URL / TOKEN cifrado" }
    eventos_hardware { enum tipo_senal "idle|inferencia|ok|alerta" }
```

## Decisiones de diseño

| Tema | Decisión | Motivo |
|---|---|---|
| Tiempo real | Pusher por defecto, Reverb por `.env`, **respaldo por sondeo** cada 5 s | Hosting compartido no permite WebSockets propios; el panel nunca queda congelado |
| ESP32 | Polling HTTPS 1 s, eventos con vigencia de 12 s | Funciona en cualquier hosting, sin puertos ni broker MQTT |
| Video móvil | `takePicture` a 2–5 fps con **una sola petición en vuelo** | No satura red ni GPU; ticks sobrantes se descartan |
| Anti-duplicados | misma placa + caseta en 60 s no se cobra de nuevo | Frames consecutivos del mismo vehículo |
| Ejes | M3 en vista lateral; frontal/posterior → ejes registrados o mínimo 2 | Regla del docente (no hay vehículos de 1 eje) |
| OCR | Corrección por posición del formato boliviano + equivalencia OCR única al cobrar | Bolivia no está en el set de entrenamiento de fast-plate-ocr |
| Privacidad M4 | Solo conteo; máscara **opaca**; se guarda solo la imagen anotada | Prohibido reconocimiento facial y recortes de rostros |
| Secretos | `.env` / `secrets.h` ignorados; `INFERENCE_TOKEN` cifrado con `APP_KEY` en BD | Penalización −10 pts |
