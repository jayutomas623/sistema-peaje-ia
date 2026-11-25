"""Paquete del servidor de inferencia del Sistema Inteligente de Control de Peaje.

M1: detección de placas (YOLOv9-t ONNX) + OCR (fast-plate-ocr)
M2: segmentación de instancias de vehículos (YOLO-seg)
M3: keypoints de llantas (YOLO-pose) + conteo de ejes
M4: segmentación y conteo de pasajeros (YOLO-seg, clase person) SIN reconocimiento facial
"""

__version__ = "1.0.0"
