# Informe técnico breve — Sistema Inteligente de Control de Peaje

**Asignatura:** IAM-811 Machine Learning y Aprendizaje Profundo · **Laboratorio Final de Hito**
**Estudiante:** ____________________ · **Modalidad:** Individual (+20 pts adicionales) · **Fecha:** ____/____/______
**Repositorio:** https://github.com/________/sistema-peaje-ia · **Subdominio:** https://____________________
**Video:** ____________________ · **Carpeta de Drive:** ____________________

> Complete las celdas `___` con los valores reales de Colab y RunPod. Exportar a PDF: VS Code “Markdown PDF”
> o `pandoc plantilla_informe_tecnico.md -o informe.pdf`.

## 1. Datasets (Roboflow)

| Modelo | Proyecto Roboflow | Tipo | Clases | Imágenes train / valid / test | Versión |
|---|---|---|---|---|---|
| M2 vehículos | ___ | Instance Segmentation | auto, minibus, bus, camion, (moto) | ___ / ___ / ___ | v___ |
| M3 llantas | ___ | Keypoint Detection (5 kps) | llanta | ___ / ___ / ___ | v___ |
| M4 pasajeros | ___ | Instance Segmentation | pasajero, vehiculo | ___ / ___ / ___ | v___ |
| M1 placas | 22 fotos del curso + ___ | (evaluación OCR) | placa | — | — |

## 2. M1 · Placas + OCR (notebook 01)

| Lector | Detección % | Exactitud cruda % | Exactitud normalizada % | CER | Latencia ms |
|---|---|---|---|---|---|
| fast-plate-ocr (venv) | ___ | ___ | ___ | ___ | ___ |
| EasyOCR (mamba) | ___ | ___ | ___ | ___ | ___ |
| Tesseract (conda) | ___ | ___ | ___ | ___ | ___ |

Confusiones más frecuentes: `___ → ___` (__ veces), `___ → ___` (__ veces).

## 3. M2 · Segmentación de vehículos (notebook 02) — `yolo11s-seg`, ___ épocas, imgsz 640

| Métrica | Box | Mask |
|---|---|---|
| mAP50 | ___ | ___ |
| mAP50-95 | ___ | ___ |

| Clase | mAP50 mask | mAP50-95 mask |
|---|---|---|
| auto | ___ | ___ |
| minibus | ___ | ___ |
| bus | ___ | ___ |
| camion | ___ | ___ |

Matriz de confusión: *(insertar `confusion_matrix_normalized.png`)*

## 4. M3 · Keypoints de llantas y ejes (notebook 03) — `yolo11s-pose`, kpt_shape [5, 3]

| mAP50 pose | mAP50-95 pose | mAP50 box | **MAE ejes** | Exactitud ejes % | n imágenes |
|---|---|---|---|---|---|
| ___ | ___ | ___ | ___ | ___ | ___ |

## 5. M4 · Pasajeros (notebook 04) — bono +5

| Modelo | mAP50 mask | mAP50-95 mask | MAE conteo | Exactitud conteo % |
|---|---|---|---|---|
| yolo11s-seg COCO (fallback) | — | — | ___ | ___ |
| m4_pasajeros_seg (fine-tuning) | ___ | ___ | ___ | ___ |

Privacidad: sin reconocimiento facial, sin recortes de rostros, máscara opaca en la evidencia.

## 6. Inferencia en RunPod (bono +5)

| Dato | Valor |
|---|---|
| Plantilla / GPU | ___ / ___ |
| URL | `https://________-8000.proxy.runpod.net` |
| Latencia calentamiento (`/health`) | ___ ms |
| Latencia `/inferir` (servidor) | ___ ms |
| Ida y vuelta app → Laravel → RunPod → app | ___ ms |

## 7. Entornos reproducibles (bono +5)

| Entorno | Archivo | Python | torch / CUDA | ultralytics | onnxruntime | Evidencia |
|---|---|---|---|---|---|---|
| venv | `requirements-venv.txt` | ___ | ___ | ___ | ___ | `evidencia_entorno_runpod-venv.txt` |
| conda | `environment-conda.yml` | ___ | ___ | ___ | ___ | `evidencia_entorno_runpod-conda.txt` |
| mamba | `environment-mamba.yml` | ___ | ___ | ___ | ___ | `evidencia_entorno_runpod-mamba.txt` |

## 8. Hardware ESP32 (bono +5)

LEDs GPIO 25/26/27 + buzzer GPIO 14 · polling HTTPS 1 s a `/api/v1/hardware/ultimo-evento` · foto del circuito: *(insertar)*.

## 9. Conclusiones

1. ___
2. ___
3. ___
