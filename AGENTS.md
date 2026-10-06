# AGENTS.md — AutoMark / doc2jats-pipeline

Contexto, arquitectura y reglas para agentes de IA que trabajen sobre el **pipeline de conversión DOCX → JATS XML**. Es uno de los dos proyectos del workspace; el otro es el plugin de OJS **JATSWizard**, que consume este pipeline por HTTP. Ver el [`AGENTS.md` global](../AGENTS.md) para la vista de conjunto.

## Qué es

Aplicación **Symfony 5.4 / PHP 7.4** (mínimo `7.2.5`) que convierte documentos DOC/DOCX a **JATS XML**, con capacidades de *automarcado* (bibliografía, citas, títulos de figuras/tablas) y publicación a HTML/PDF. Se construye sobre una versión muy modificada de [`Vitaliy-1/docxToJats`](https://github.com/Vitaliy-1/docxToJats) (dependencia Composer `doc/docx2jats`, rama `dev-scielo`) e integra herramientas externas (LibreOffice, pandoc, exiftool, [AnyStyle](https://github.com/inukshuk/anystyle)).

Se usa de **dos formas**, ambas sobre el mismo código de servicios:
- **Comandos CLI** (`bin/console doc:*`, `jats:publisher`). Pueden ejecutarse localmente **o** delegar en un servidor remoto (ver "Ejecución remota").
- **Endpoints HTTP** bajo `/doc/*`, que es como los consume el plugin JATSWizard.

## Arquitectura

Layout estándar de Symfony con el dominio en `src/Service/`, organizado por **capas hexagonales**:

- `App/` — servicios de **aplicación** (orquestación de casos de uso). Ej.: `JatsConverter`, `Anonymizer`, `DocxConverter`, `JatsPublisher`, `Automark`, `CslGenerator`.
- `Dom/` — **dominio**: lógica pura, entidades e **interfaces** de los puertos (ej. `ExternalCommand/LibreOfficeInterface`, `CslRepositoryInterface`, `CitationStyle/*Interface`).
- `Inf/` — **infraestructura**: implementaciones concretas de los puertos (ej. `LibreOffice`, `Pandoc`, `ExifTool`, `AnystyleCslExtractor`, estilos de cita `Ama`/`Apa`/`Vancouver`).

Dos módulos de servicio principales:
- `src/Service/DocConversion/` — conversión de documentos (normalización, docx→jats, anonimización a PDF, publicación).
- `src/Service/Automark/` — detección de bibliografía, generación de citas/referencias, captions de figuras/tablas, mapeos SciELO.

Otras carpetas:
- `src/Command/` — comandos de consola. `AbstractBatchDocConverterCommand` + `RemoteConverterTrait` dan el soporte de ejecución local/remota por lotes.
- `src/Controller/DocumentConverterController.php` — **todos** los endpoints HTTP (`/doc/` con rutas por anotación: `status`, `anonymizer`, `normalizer`, `tojats`, `jatsPublisher`, `jatsPublisherV2`).
- `src/Form/` — Symfony Forms que validan los `multipart/form-data` de los endpoints (ej. `DocToJatsFormType` → campos `doc_to_jats_form[...]`).
- `src/Validator/`, `src/Component/` — validación de ficheros y utilidades (XML, property access).
- `config/automark/` — configuración **cargada automáticamente** de todos los YAML del directorio: `keywords/` (palabras clave por idioma para detectar títulos y bibliografía) y mapeos (`ScieloMappings.yaml`).
- `format-jats/` (raíz del proyecto) — **bundle compilado** (zero-deps) del publicador JATS→HTML/PDF. **No se edita a mano.**
- `tools/format-jats/` — **código fuente TypeScript** de ese publicador. Tiene su propio [`AGENTS.md`](tools/format-jats/.agents/AGENTS.md); respétalo al tocar esa herramienta. Se despliega con `npm run deploy` (reconstruye y copia a `../../format-jats/`).
- `docker/` — imagen `php:7.4-apache` con todas las herramientas externas.

## Flujo de conversión (doc:tojats)

`DocToJatsCommand` / endpoint `/doc/tojats` → `JatsConverter` orquesta:
1. (Opcional) **Normalización** con LibreOffice (`--normalize`): re-guarda el DOCX para limpiar su estructura.
2. **docx→jats** vía `DocxToJats` (envoltura de `docx2jats`).
3. **AutoMark** (si hay opciones activas): detecta bibliografía con AnyStyle, marca citas según el estilo (`apa`/`ama`/`vancouver`), detecta títulos de figuras/tablas y los sustituye por referencias, aplica mapeos SciELO.
4. **Fusión del `<front>`** si se pasa un XML de metadatos (`--front`).
5. Salida: `article.xml` (+ imágenes, + `article.json` con el CSL detectado) empaquetado en ZIP para los endpoints HTTP.

## Ejecución remota

Los comandos CLI pueden actuar como **cliente HTTP de sí mismos**: si la variable de entorno de URL correspondiente está definida, el comando hace POST al endpoint remoto en vez de ejecutar localmente. Mapeo comando ↔ endpoint ↔ env:

| Comando | Endpoint | Env URL |
|---|---|---|
| `doc:anonymizer` | `/doc/anonymizer` | `ANONIMIZER_URL` |
| `doc:normalizer` | `/doc/normalizer` | `NORMALIZER_URL` |
| `doc:tojats` | `/doc/tojats` | `DOCTOJATS_URL` |
| `jats:publisher` | `/doc/jatsPublisher` | `JATSPUBLISHER_URL` |

## Configuración

- Symfony por entorno: **no edites `.env`**; usa `.env.local` / `.env.prod.local`. Claves relevantes: `APP_ENV`, `CONVERSION_TIMEOUT`, binarios externos (`ANYSTYLE_BIN`, `EXIFTOOL_BIN`, `LIBREOFFICE_BIN`, `LIBREOFFICE_HOME`, `PANDOC_BIN`) y las `*_URL` de ejecución remota.
- Parámetros y bindings de servicios en `config/services.yaml`.
- Automark: idiomas y keywords en `config/automark/keywords/*.yaml`; mapeos en `config/automark/*.yaml`.

## Comandos de desarrollo

```bash
composer install                       # dependencias
bin/console list doc                   # listar comandos doc:*
bin/console list jats                  # listar comandos jats:*
bin/console doc:tojats --help          # opciones de un comando
php bin/phpunit                        # tests (PHPUnit 9.5, config en phpunit.xml.dist)
docker compose up -d                   # stack completo; herramientas online en el puerto 8000
```

Para la herramienta `format-jats` (dentro de `tools/format-jats/`): `npm test` (vitest), `npm run deploy` para reconstruir el bundle.

## Reglas para agentes

1. **Respeta las capas.** El dominio (`Dom/`) no depende de infraestructura; las implementaciones viven en `Inf/` y se inyectan por sus interfaces. No metas llamadas a procesos externos ni acceso a red en `Dom/`.
2. **Herramientas externas solo tras su interfaz** (`Dom/ExternalCommand/*Interface` → `Inf/*`). Los binarios se resuelven por parámetro inyectado desde `services.yaml`, nunca con rutas hardcodeadas.
3. **Config por YAML, no por código.** Añadir idiomas/keywords/mapeos = nuevos YAML en `config/automark/`; se cargan solos.
4. **No edites `format-jats/`** (bundle generado). Edita el fuente en `tools/format-jats/` y ejecuta `deploy`.
5. **Seguridad conocida pendiente** (ver `../docs/informe_pipeline.md`): la extracción de ZIP (`ZipArchive::extractTo`) en el controlador y en `RemoteConverterTrait` es vulnerable a **Zip Slip**; valida rutas dentro del `workdir` canónico al tocar esa zona. Prefiere *streaming* a `Response::getContent()` para ZIPs grandes, y limpieza con `try/finally` en vez de `register_shutdown_function`.
6. **Compatibilidad PHP 7.4 / Symfony 5.4.** No introduzcas sintaxis de PHP 8+ ni APIs de Symfony 6+ sin acordarlo; la actualización está en el TODO pero aún no hecha.
7. **Contrato con JATSWizard.** Los nombres de campo de formulario (`doc_to_jats_form[...]`, `upload_zip_file_form[...]`) y el formato de respuesta (ZIP con `article.xml`/`article.json`) son el **contrato con el plugin**. Si los cambias, actualiza también `JATSWizard/classes/PipelineApiClient.inc.php`.
8. **Tests.** Añade/actualiza pruebas en `tests/` (PHPUnit) para lógica nueva relevante.
9. **Documenta en español** cuando amplíes esta guía o el README, para mantener coherencia con el proyecto.
