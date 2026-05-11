<?php 
class Clientes {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // Método para consultar todos los clientes


// METODO CONSULTAR
public function consultar() {
    $sql = "SELECT 
                c.id_cliente,
                c.documento,
                c.nombres,
                c.apellidos,
                c.direccion,
                c.telefono,
                c.telefono2,
                c.id_ruta,
                c.id_usuario,
                c.orden_cobranza,
                c.activo,
                c.fecha_registro,
                c.fecha_cancelacion,
                c.foto_cliente,
                c.foto_cedula_frontal,
                c.foto_cedula_atras,
                c.id_fiador,
                c.latitud,
                c.longitud,
                c.fecha_ubicacion,
                r.nombre_ruta,
                f.documento AS documento_fiador,
                CASE 
                    WHEN f.nombres IS NOT NULL AND f.apellidos IS NOT NULL 
                    THEN CONCAT(f.nombres, ' ', f.apellidos)
                    ELSE NULL
                END AS nombre_completo_fiador,
                f.telefono AS telefono_fiador,
                f.direccion AS direccion_fiador,
                f.foto_fiador,
                f.foto_cedula_frontal AS foto_cedula_frontal_fiador,
                f.foto_cedula_atras AS foto_cedula_atras_fiador
            FROM clientes c
            LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
            LEFT JOIN fiadores f ON c.id_fiador = f.id_fiador
            ORDER BY c.id_cliente DESC";
    $stmt = $this->conexion->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// METODO CONSULTAR POR ID
public function consultarPorId($id) {
    $sql = "SELECT 
                c.id_cliente,
                c.documento,
                c.nombres,
                c.apellidos,
                c.direccion,
                c.telefono,
                c.telefono2,
                c.id_ruta,
                c.id_usuario,
                c.orden_cobranza,
                c.activo,
                c.fecha_registro,
                c.fecha_cancelacion,
                c.foto_cliente,
                c.foto_cedula_frontal,
                c.foto_cedula_atras,
                c.id_fiador,
                c.latitud,
                c.longitud,
                c.fecha_ubicacion,
                r.nombre_ruta,
                f.documento AS documento_fiador,
                f.nombres AS nombres_fiador,
                f.apellidos AS apellidos_fiador,
                f.telefono AS telefono_fiador,
                f.telefono2 AS telefono2_fiador,
                f.direccion AS direccion_fiador,
                f.foto_fiador,
                f.foto_cedula_frontal AS foto_cedula_frontal_fiador,
                f.foto_cedula_atras AS foto_cedula_atras_fiador
            FROM clientes c
            LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
            LEFT JOIN fiadores f ON c.id_fiador = f.id_fiador
            WHERE c.id_cliente = :id
            LIMIT 1";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// METODO ELIMINAR
public function eliminar($id) {
    $sql = "DELETE FROM clientes WHERE id_cliente = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    return [
        "resultado" => "Cliente eliminado correctamente",
        "mensaje"   => "El cliente ha sido eliminado de la base de datos"
    ];
}

// METODO INSERTAR
public function insertar($params) {
    $sql = "INSERT INTO clientes (
                documento, nombres, apellidos, direccion, telefono, telefono2,
                id_ruta, id_usuario, orden_cobranza, activo,
                foto_cliente, foto_cedula_frontal, foto_cedula_atras, id_fiador,
                latitud, longitud, fecha_ubicacion
            ) VALUES (
                :documento, :nombres, :apellidos, :direccion, :telefono, :telefono2,
                :id_ruta, :id_usuario, :orden_cobranza, :activo,
                :foto_cliente, :foto_cedula_frontal, :foto_cedula_atras, :id_fiador,
                :latitud, :longitud, :fecha_ubicacion
            )";

    $stmt = $this->conexion->prepare($sql);

    // Validar campos obligatorios
    if (empty($params['documento']) || empty($params['nombres']) || empty($params['apellidos'])) {
        throw new Exception("Los campos documento, nombres y apellidos son obligatorios");
    }

    $documento = $params['documento'];
    $nombres = $params['nombres'];
    $apellidos = $params['apellidos'];
    $direccion = $params['direccion'] ?? null;
    $telefono = $params['telefono'] ?? null;
    $telefono2 = $params['telefono2'] ?? null;
    $id_ruta = !empty($params['id_ruta']) ? (int)$params['id_ruta'] : null;
    $id_usuario = !empty($params['id_usuario']) ? (int)$params['id_usuario'] : null;
    $orden_cobranza = !empty($params['orden_cobranza']) ? (int)$params['orden_cobranza'] : 0;
    $activo = isset($params['activo']) ? (int)$params['activo'] : 1;
    $foto_cliente = $params['foto_cliente'] ?? null;
    $foto_cedula_frontal = $params['foto_cedula_frontal'] ?? null;
    $foto_cedula_atras = $params['foto_cedula_atras'] ?? null;
    $id_fiador = !empty($params['id_fiador']) ? (int)$params['id_fiador'] : null;
    $latitud = !empty($params['latitud']) ? (float)$params['latitud'] : null;
    $longitud = !empty($params['longitud']) ? (float)$params['longitud'] : null;
    $fecha_ubicacion = (!empty($latitud) && !empty($longitud)) ? date('Y-m-d H:i:s') : null;

    $stmt->bindParam(":documento", $documento);
    $stmt->bindParam(":nombres", $nombres);
    $stmt->bindParam(":apellidos", $apellidos);
    $stmt->bindParam(":direccion", $direccion);
    $stmt->bindParam(":telefono", $telefono);
    $stmt->bindParam(":telefono2", $telefono2);
    $stmt->bindParam(":id_ruta", $id_ruta, PDO::PARAM_INT);
    $stmt->bindParam(":id_usuario", $id_usuario, PDO::PARAM_INT);
    $stmt->bindParam(":orden_cobranza", $orden_cobranza, PDO::PARAM_INT);
    $stmt->bindParam(":activo", $activo, PDO::PARAM_INT);
    $stmt->bindParam(":foto_cliente", $foto_cliente);
    $stmt->bindParam(":foto_cedula_frontal", $foto_cedula_frontal);
    $stmt->bindParam(":foto_cedula_atras", $foto_cedula_atras);
    $stmt->bindParam(":id_fiador", $id_fiador, PDO::PARAM_INT);
    $stmt->bindParam(":latitud", $latitud);
    $stmt->bindParam(":longitud", $longitud);
    $stmt->bindParam(":fecha_ubicacion", $fecha_ubicacion);

    $stmt->execute();

    return [
        "resultado" => "Cliente insertado correctamente",
        "mensaje"   => "El cliente ha sido agregado a la base de datos"
    ];
}

// METODO EDITAR
public function editar($id, $params) {
    $sql = "UPDATE clientes SET
                documento = :documento,
                nombres = :nombres,
                apellidos = :apellidos,
                direccion = :direccion,
                telefono = :telefono,
                telefono2 = :telefono2,
                id_ruta = :id_ruta,
                orden_cobranza = :orden_cobranza,
                activo = :activo,
                fecha_cancelacion = :fecha_cancelacion,
                foto_cliente = :foto_cliente,
                foto_cedula_frontal = :foto_cedula_frontal,
                foto_cedula_atras = :foto_cedula_atras,
                id_fiador = :id_fiador,
                latitud = :latitud,
                longitud = :longitud,
                fecha_ubicacion = :fecha_ubicacion
            WHERE id_cliente = :id";

    $stmt = $this->conexion->prepare($sql);

    $latitud = !empty($params['latitud']) ? (float)$params['latitud'] : null;
    $longitud = !empty($params['longitud']) ? (float)$params['longitud'] : null;
    $fecha_ubicacion = (!empty($latitud) && !empty($longitud)) ? date('Y-m-d H:i:s') : null;

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":documento", $params['documento']);
    $stmt->bindParam(":nombres", $params['nombres']);
    $stmt->bindParam(":apellidos", $params['apellidos']);
    $stmt->bindParam(":direccion", $params['direccion']);
    $stmt->bindParam(":telefono", $params['telefono']);
    $stmt->bindParam(":telefono2", $params['telefono2']);
    $stmt->bindParam(":id_ruta", $params['id_ruta'], PDO::PARAM_INT);
    $stmt->bindParam(":orden_cobranza", $params['orden_cobranza'], PDO::PARAM_INT);
    $stmt->bindParam(":activo", $params['activo'], PDO::PARAM_INT);
    $stmt->bindParam(":fecha_cancelacion", $params['fecha_cancelacion']);
    $stmt->bindParam(":foto_cliente", $params['foto_cliente']);
    $stmt->bindParam(":foto_cedula_frontal", $params['foto_cedula_frontal']);
    $stmt->bindParam(":foto_cedula_atras", $params['foto_cedula_atras']);
    $stmt->bindParam(":id_fiador", $params['id_fiador'], PDO::PARAM_INT);
    $stmt->bindParam(":latitud", $latitud);
    $stmt->bindParam(":longitud", $longitud);
    $stmt->bindParam(":fecha_ubicacion", $fecha_ubicacion);

    $stmt->execute();

    return [
        "resultado" => "Cliente editado correctamente",
        "mensaje"   => "El cliente ha sido actualizado en la base de datos"
    ];
}

// METODO ACTUALIZAR UBICACION
public function actualizarUbicacion($id, $latitud, $longitud) {
    $sql = "UPDATE clientes SET
                latitud = :latitud,
                longitud = :longitud,
                fecha_ubicacion = NOW()
            WHERE id_cliente = :id";

    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":latitud", $latitud);
    $stmt->bindParam(":longitud", $longitud);
    $stmt->execute();

    return [
        "resultado" => "ok",
        "mensaje" => "Ubicación actualizada correctamente"
    ];
}

// METODO CONSULTAR CLIENTES CON UBICACION
public function consultarConUbicacion($id_ruta = null) {
    $sql = "SELECT 
                c.id_cliente,
                c.documento,
                c.nombres,
                c.apellidos,
                c.direccion,
                c.telefono,
                c.latitud,
                c.longitud,
                c.fecha_ubicacion,
                r.nombre_ruta
            FROM clientes c
            LEFT JOIN rutas r ON c.id_ruta = r.id_ruta
            WHERE c.activo = 1 
            AND c.latitud IS NOT NULL 
            AND c.longitud IS NOT NULL";
    
    if ($id_ruta !== null) {
        $sql .= " AND c.id_ruta = :id_ruta";
    }
    
    $sql .= " ORDER BY c.nombres, c.apellidos";
    
    $stmt = $this->conexion->prepare($sql);
    
    if ($id_ruta !== null) {
        $stmt->bindParam(":id_ruta", $id_ruta, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// METODO FILTRAR
public function filtrar($valor) {
    $sql = "SELECT * FROM clientes
            WHERE nombres LIKE :valor
               OR apellidos LIKE :valor
               OR documento LIKE :valor";

    $stmt = $this->conexion->prepare($sql);
    $like = "%$valor%";
    $stmt->bindParam(":valor", $like);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// METODO BUSCAR POR DOCUMENTO
public function buscarPorDocumento($documento) {
    $sql = "SELECT * FROM clientes WHERE documento = :documento LIMIT 1";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":documento", $documento);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// METODO ACTUALIZAR SOLO FIADOR
public function actualizarFiador($id_cliente, $id_fiador) {
    $sql = "UPDATE clientes SET id_fiador = :id_fiador WHERE id_cliente = :id_cliente";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id_cliente", $id_cliente, PDO::PARAM_INT);
    $stmt->bindParam(":id_fiador", $id_fiador, PDO::PARAM_INT);
    $stmt->execute();

    return [
        "resultado" => "Fiador asignado correctamente",
        "mensaje"   => "El fiador ha sido asignado al cliente existente"
    ];
}

// METODO OBTENER ULTIMO ID INSERTADO
public function obtenerUltimoId() {
    return $this->conexion->lastInsertId();
}

// METODO ACTIVAR CLIENTE
public function activar($id) {
    $sql = "UPDATE clientes SET activo = 1 WHERE id_cliente = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    return [
        "resultado" => "success",
        "mensaje" => "Cliente activado correctamente"
    ];
}

// METODO INACTIVAR CLIENTE
public function inactivar($id) {
    $sql = "UPDATE clientes SET activo = 0, fecha_cancelacion = NOW() WHERE id_cliente = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    return [
        "resultado" => "success",
        "mensaje" => "Cliente inactivado correctamente"
    ];
}
}

?>