# Peluquería Manager (PHP)

Base inicial en **PHP + SQLite** con menús por ventanas (widgets):

- **Colaboradores**: agregar, editar, eliminar y registrar trabajos por colaborador.
- **Servicios**: agregar, editar y eliminar servicios con precio y % para colaborador.
- **Productos**: agregar, editar y eliminar productos con descripción, costo, venta y stock.
- **Contabilidad**: debe/haber manual + haber automático por trabajos.
- **Reportes**: reporte semanal y mensual por colaborador.

## Requisitos

- PHP 8.1+ (incluyendo extensión SQLite/PDO SQLite).

## Ejecutar

```bash
php -S 0.0.0.0:4173
```

Abrir: `http://localhost:4173/index.php`

## Nota

Esta es una primera base para iterar "de a poco". En siguientes pasos podemos mejorar:

- Búsquedas/autocompletado de colaboradores/servicios.
- Venta real de productos con kardex de movimientos.
- Autenticación por roles (admin/colaborador).
