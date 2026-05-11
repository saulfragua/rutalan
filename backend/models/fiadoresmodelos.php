<?php 
class Fiadores {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // Método para consultar todas las rutas


// METODO CONSULTAR
public function consultar() {
    $sql = "SELECT * FROM fiadores";
    $stmt = $this->conexion->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// METODO ELIMINAR
public function eliminar($id) {
    $sql = "DELETE FROM fiadores WHERE id_fiador = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    return [
        "resultado" => "Fiador eliminado correctamente",
        "mensaje"   => "El fiador ha sido eliminado de la base de datos"
    ];
}

// METODO INSERTAR
public function insertar($params) {
    $sql = "INSERT INTO fiadores (
                documento, nombres, apellidos, direccion,
                telefono, telefono2, activo,
                foto_fiador, foto_cedula_frontal, foto_cedula_atras
            ) VALUES (
                :documento, :nombres, :apellidos, :direccion,
                :telefono, :telefono2, :activo,
                :foto_fiador, :foto_cedula_frontal, :foto_cedula_atras
            )";

    // Validar campos obligatorios
    if (empty($params['documento']) || empty($params['nombres']) || empty($params['apellidos'])) {
        throw new Exception("Los campos documento, nombres y apellidos son obligatorios");
    }

    $stmt = $this->conexion->prepare($sql);

    $documento = $params['documento'];
    $nombres = $params['nombres'];
    $apellidos = $params['apellidos'];
    $direccion = $params['direccion'] ?? null;
    $telefono = $params['telefono'] ?? null;
    $telefono2 = $params['telefono2'] ?? null;
    $activo = isset($params['activo']) ? (int)$params['activo'] : 1;
    $foto_fiador = $params['foto_fiador'] ?? null;
    $foto_cedula_frontal = $params['foto_cedula_frontal'] ?? null;
    $foto_cedula_atras = $params['foto_cedula_atras'] ?? null;

    $stmt->bindParam(":documento", $documento);
    $stmt->bindParam(":nombres", $nombres);
    $stmt->bindParam(":apellidos", $apellidos);
    $stmt->bindParam(":direccion", $direccion);
    $stmt->bindParam(":telefono", $telefono);
    $stmt->bindParam(":telefono2", $telefono2);
    $stmt->bindParam(":activo", $activo, PDO::PARAM_INT);
    $stmt->bindParam(":foto_fiador", $foto_fiador);
    $stmt->bindParam(":foto_cedula_frontal", $foto_cedula_frontal);
    $stmt->bindParam(":foto_cedula_atras", $foto_cedula_atras);

    $stmt->execute();

    return [
        "resultado" => "Fiador insertado correctamente",
        "mensaje"   => "El fiador ha sido agregado a la base de datos"
    ];
}

// METODO EDITAR
public function editar($id, $params) {
    $sql = "UPDATE fiadores SET
                documento = :documento,
                nombres = :nombres,
                apellidos = :apellidos,
                direccion = :direccion,
                telefono = :telefono,
                telefono2 = :telefono2,
                activo = :activo,
                foto_fiador = :foto_fiador,
                foto_cedula_frontal = :foto_cedula_frontal,
                foto_cedula_atras = :foto_cedula_atras
            WHERE id_fiador = :id";

    $stmt = $this->conexion->prepare($sql);

    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->bindParam(":documento", $params['documento']);
    $stmt->bindParam(":nombres", $params['nombres']);
    $stmt->bindParam(":apellidos", $params['apellidos']);
    $stmt->bindParam(":direccion", $params['direccion']);
    $stmt->bindParam(":telefono", $params['telefono']);
    $stmt->bindParam(":telefono2", $params['telefono2']);
    $stmt->bindParam(":activo", $params['activo'], PDO::PARAM_INT);
    $stmt->bindParam(":foto_fiador", $params['foto_fiador']);
    $stmt->bindParam(":foto_cedula_frontal", $params['foto_cedula_frontal']);
    $stmt->bindParam(":foto_cedula_atras", $params['foto_cedula_atras']);

    $stmt->execute();

    return [
        "resultado" => "Fiador editado correctamente",
        "mensaje"   => "El fiador ha sido actualizado en la base de datos"
    ];
}

// METODO FILTRAR
public function filtrar($valor) {
    $sql = "SELECT * FROM fiadores
            WHERE documento LIKE :valor
                OR nombres LIKE :valor
                OR apellidos LIKE :valor";

    $stmt = $this->conexion->prepare($sql);
    $like = "%$valor%";
    $stmt->bindParam(":valor", $like);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// METODO BUSCAR POR DOCUMENTO
public function buscarPorDocumento($documento) {
    $sql = "SELECT * FROM fiadores WHERE documento = :documento LIMIT 1";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(":documento", $documento);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// METODO OBTENER ULTIMO ID INSERTADO
public function obtenerUltimoId() {
    return $this->conexion->lastInsertId();
}
}

?>