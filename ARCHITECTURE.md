# Arquitectura - Resumen

Breve descripción de la arquitectura actual y decisiones recomendadas para migración y despliegue.

## Resumen del sistema
- Backend: Laravel (PHP) — código existente en este repositorio.
- Frontend: Blade + Vue (componentes en `resources/js/components`), Ziggy para rutas JS.
- Base de datos: MySQL (esquema relacional). Traducciones en columnas `TEXT` manejadas por `HasTranslatableJson`.
- Almacenamiento de archivos: `app/Services/UploadedFileService.php`.

## Objetivo de migración
- Migrar lógica de negocio a un servicio moderno (p.ej. Python + FastAPI) manteniendo paridad funcional.
- Proveer API REST/OpenAPI, pruebas automatizadas y despliegue reproducible en VPS/containers.

## Componentes y responsabilidades
- API: Endpoints REST siguiendo OpenAPI. Autenticación por tokens/sesión según alcance.
- Worker/Jobs: Procesos asíncronos para notificaciones, webhooks y envíos (pendiente definir si se migra a Celery/RQ o similar).
- Integraciones externas: Stripe (pagos), CRM (Zoho), WhatsApp/LLM/voz (opcional sandbox).
- Frontend admin: Vue components con modales autónomos realizando AJAX; toasts y autosave global.

## Despliegue recomendado (mínimo viable)
- Dockerizar la app y usar `docker-compose` para desarrollo: servicios `app`, `nginx`, `mysql`.
- En VPS: desplegar containers o construir imágenes y usar sistema de procesos (systemd) si no se usan containers.
- Variables sensibles vía entorno (`.env`) y no en el repo.

## Notas de migración
- Priorizar replicar la lógica de negocio (endpoints críticos: gestión de planes, coberturas, suscripciones).
- Crear pruebas de regresión (unit + integration) contra la API actual para validar paridad.
- Documentar decisiones en este archivo y en un `DECISIONS.md` si hay cambios arquitectónicos.

## Checkpoints mínimos para entrega
1. API funcional desplegada en VPS.
2. Conexión MySQL configurada y migraciones aplicadas.
3. Repositorio con historial claro y README con pasos de despliegue.

---
Fecha: 2026-03-02
