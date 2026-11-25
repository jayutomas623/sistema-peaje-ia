# Datasets públicos usados (Roboflow Universe)

Los modelos M2, M3 y M4 se entrenan o evalúan con datasets **públicos ya etiquetados** de Roboflow Universe,
citados aquí con su autor y licencia. Los notebooks los descargan con la API key del usuario (secreto de Colab
`ROBOFLOW_API_KEY`); **ninguna imagen de estos datasets se versiona en el repositorio**.
M1 (placas) no usa dataset de entrenamiento: emplea modelos ONNX preentrenados verificados por SHA-256
(ver `01_m1_placas_bolivia_ocr.ipynb`) y se evalúa con las 22 fotos bolivianas de
`web/database/seeders/evidencias/`.

| Modelo | Dataset | Autor | Versión · formato | Licencia | Uso |
|---|---|---|---|---|---|
| **M2** vehículos | [Vehicle Segmentation](https://universe.roboflow.com/akash-bauri-nyofc/vehicle-segmentation) | Akash Bauri | v4 · `yolov11` (polígonos) | CC BY 4.0 | Entrenamiento `yolo11s-seg` → `m2_vehiculos_seg.pt` |
| **M3** llantas / ejes | [axle](https://universe.roboflow.com/eternal-wiecw/axle-bjpov) | eternal | v5 · `yolov11` (cajas) | **No indicada en la página de Universe** | Entrenamiento `yolo11s-pose` con keypoints derivados de cajas → `m3_llantas_pose.pt` |
| **M4** personas | [Car and Persons](https://universe.roboflow.com/yolov8-carsperson/car-and-persons) | YOLOv8 CarsPerson | v1 · `yolov11` (cajas) | CC BY 4.0 | Solo **evaluación** del conteo con `yolo11s-seg` COCO (sin fine-tuning) |

## M2 · Vehicle Segmentation (Akash Bauri)

* **URL:** <https://universe.roboflow.com/akash-bauri-nyofc/vehicle-segmentation> · workspace `akash-bauri-nyofc`,
  proyecto `vehicle-segmentation`, **versión 4** (el notebook lista las versiones publicadas y avisa si hay una más nueva).
* **Licencia:** CC BY 4.0 — uso permitido citando al autor.
* **Mapeo de clases** (constante `MAPEO_M2` editable al inicio del notebook 02):

  | Categoría del peaje | Clases del dataset |
  |---|---|
  | `auto` | `car`, `sedan`, `hatchback`, `suv`, `swift`, `thar`, `scorpio`, `bolero`, `pickup` |
  | `minibus` | `van`, `omni` |
  | `bus` | `bus` |
  | `camion` | `truck` |
  | *descartadas* | `auto` (**auto-rickshaw / tuk-tuk**, no es un automóvil), `motorbike`, `bike-rider`, `motorbike-rider`, `scooty`, `scooty-rider`, `apache`, `bullet`, `pulsar`, `person`, `man`, `woman`, `child`, `helmet`, `no-helmet`, `license-plate`, `tractor`, `jcb` |

  Cualquier otra clase no listada también se descarta (el notebook la informa). Se entrena con las categorías
  que tienen **≥ 30 instancias en train** (mínimo 3); si `minibus` no alcanza, se entrena sin ella con aviso.
* **Dominio:** tráfico de la India. Por eso el notebook mide además la exactitud en las 22 fotos bolivianas.

```bibtex
@misc{bauri2024vehiclesegmentation,
  title        = {Vehicle Segmentation Dataset},
  author       = {Akash Bauri},
  howpublished = {Roboflow Universe},
  url          = {https://universe.roboflow.com/akash-bauri-nyofc/vehicle-segmentation},
  note         = {Versión 4, licencia CC BY 4.0},
  year         = {2024}
}
```

## M3 · axle (eternal)

* **URL:** <https://universe.roboflow.com/eternal-wiecw/axle-bjpov> · workspace `eternal-wiecw`, proyecto
  `axle-bjpov`, **versión 5**.
* **Licencia:** la página de Roboflow Universe **no muestra licencia**. Se usa con fines académicos citando al
  autor; si se publica el modelo fuera del curso, confirmar la licencia con el autor.
* **Clases:** `]`, `axle`, `B` → se conserva **solo `axle`** (buscada por nombre en `data.yaml`).
* **Conversión:** el dataset trae cajas, no keypoints. Cada caja se convierte en 5 keypoints geométricos en el
  orden del servidor: 0 centro `(cx, cy)`, 1 contacto `(cx, cy + h/2)`, 2 superior `(cx, cy − h/2)`,
  3 izquierda `(cx − w/2, cy)`, 4 derecha `(cx + w/2, cy)`; visibilidad 2, recortados a [0, 1];
  `kpt_shape [5, 3]`, `flip_idx [0, 1, 2, 4, 3]`, clase `llanta`.
* **Limitación:** los keypoints están **derivados de cajas**, no etiquetados a mano
  (`keypoints_origen` en `m3_metricas.json`). El MAE de ejes compara las cajas `axle` por imagen con el conteo
  del servidor (`DetectorEjes`).

```bibtex
@misc{eternal2024axle,
  title        = {axle Dataset},
  author       = {eternal},
  howpublished = {Roboflow Universe},
  url          = {https://universe.roboflow.com/eternal-wiecw/axle-bjpov},
  note         = {Versión 5, licencia no indicada en la página},
  year         = {2024}
}
```

## M4 · Car and Persons (YOLOv8 CarsPerson)

* **URL:** <https://universe.roboflow.com/yolov8-carsperson/car-and-persons> · workspace `yolov8-carsperson`,
  proyecto `car-and-persons`, **versión 1**.
* **Licencia:** CC BY 4.0.
* **Clases:** se usa solo `Person` (sin distinguir mayúsculas). GT de conteo = número de cajas `Person` por imagen.
* **Uso:** solo evaluación de `yolo11s-seg` preentrenado en COCO (clase 0 = `person`, conf ≥ 0.25) en valid y
  test. El fine-tuning queda desactivado (`HACER_FINETUNE = False`).
* **Limitación:** son peatones y autos en la calle, **no pasajeros dentro de un vehículo**.
* **Privacidad:** sin reconocimiento facial; no se guardan recortes; visualización con máscara opaca.

```bibtex
@misc{carsperson2024carandpersons,
  title        = {Car and Persons Dataset},
  author       = {{YOLOv8 CarsPerson}},
  howpublished = {Roboflow Universe},
  url          = {https://universe.roboflow.com/yolov8-carsperson/car-and-persons},
  note         = {Versión 1, licencia CC BY 4.0},
  year         = {2024}
}
```

## Resultados

Los notebooks guardan las métricas en `MyDrive/peaje-ia/metricas/` (`m2_metricas.json`, `m3_metricas.json`,
`m4_metricas.json` y sus CSV). Este archivo no reporta números: se completan con lo que produzca cada ejecución.
