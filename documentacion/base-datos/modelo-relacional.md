# Modelo relacional — Base de datos `ebike_management`

## Diagrama Entidad-Relación (Mermaid)

```mermaid
erDiagram
    ROLES {
        int id_rol PK
        varchar codigo_rol
        varchar nombre
    }
    USUARIOS {
        int id_usuario PK
        varchar nombre
        int id_rol FK
    }
    BICICLETAS {
        int id_bicicleta PK
        varchar codigo_qr UK
        int nivel_bateria
        varchar estado
        int id_estacion FK
    }
    ESTACIONES {
        int id_estacion PK
        varchar nombre
        int capacidad
    }
    ALQUILERES {
        int id_alquiler PK
        uuid uuid_alquiler
        datetime fecha_inicio
        datetime fecha_fin
        decimal costo_total
        varchar estado_alquiler
        int id_usuario FK
        int id_bicicleta FK
        int id_estacion_origen FK
        int id_estacion_destino FK
    }

    ROLES ||--o{ USUARIOS : "tiene"
    ESTACIONES ||--o{ BICICLETAS : "almacena"
    USUARIOS ||--o{ ALQUILERES : "realiza"
    BICICLETAS ||--o{ ALQUILERES : "es alquilada"
    ESTACIONES ||--o{ ALQUILERES : "origen"
    ESTACIONES ||--o{ ALQUILERES : "destino"
```

## Descripción de tablas

| Tabla | Descripción | Clave primaria |
|---|---|---|
| `roles` | Perfiles de usuario (ADMIN, SUPERVISOR, …) | `id_rol` |
| `usuarios` | Clientes del sistema | `id_usuario` |
| `bicicletas` | Flota de bicicletas eléctricas | `id_bicicleta` |
| `estaciones` | Puntos de alquiler/devolución | `id_estacion` |
| `alquileres` | Cada uso de una bicicleta | `id_alquiler` |

## Relaciones principales
- Un **rol** puede tener muchos **usuarios** (1:N).
- Una **estación** almacena muchas **bicicletas** (1:N).
- Un **usuario** realiza muchos **alquileres** (1:N).
- Una **bicicleta** participa en muchos **alquileres** (1:N).
- Un **alquiler** tiene estación de origen y de destino (N:1 hacia `estaciones`).

## Procedimientos almacenados relevantes
- `sp_reporte_estadisticas_admin`, `sp_alquiler_buscar_paginado`,
  `sp_histograma_duraciones`, `sp_indicadores_sistema`, `sp_validar_rol`.
