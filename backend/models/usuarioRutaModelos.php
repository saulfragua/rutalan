<?php
class UsuarioRuta {

    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    
    // CONSULTAR TODAS LAS ASIGNACIONES
    
    public function consultar() {

        $sql = "SELECT ur.id_usuario, u.nombre_completo,
                       ur.id_ruta, r.nombre_ruta,
                       ur.fecha_asignacion
                FROM usuario_ruta ur
                INNER JOIN usuarios u ON u.id_usuario = ur.id_usuario
                INNER JOIN rutas r ON r.id_ruta = ur.id_ruta";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    // ELIMINAR ASIGNACIÓN USUARIO-RUTA
    
    public function eliminar($params) {

        $sql = "DELETE FROM usuario_ruta
                WHERE id_usuario = :id_usuario
                AND id_ruta = :id_ruta";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_usuario', $params['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_ruta', $params['id_ruta'], PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Asignación eliminada";
        $vec['mensaje'] = "La ruta fue retirada del usuario correctamente";

        return $vec;
    }

    
    // INSERTAR ASIGNACIÓN
    
    public function insertar($params) {

        $sql = "INSERT INTO usuario_ruta (id_usuario, id_ruta)
                VALUES (:id_usuario, :id_ruta)";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_usuario', $params['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_ruta', $params['id_ruta'], PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Ruta asignada";
        $vec['mensaje'] = "La ruta fue asignada al usuario correctamente";

        return $vec;
    }

    
    // EDITAR ASIGNACIÓN (CAMBIAR RUTA)
    
    public function editar($params) {

        $sql = "UPDATE usuario_ruta
                SET id_ruta = :nueva_ruta
                WHERE id_usuario = :id_usuario
                AND id_ruta = :ruta_actual";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':nueva_ruta', $params['nueva_ruta'], PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario', $params['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':ruta_actual', $params['ruta_actual'], PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Asignación actualizada";
        $vec['mensaje'] = "La ruta del usuario fue modificada correctamente";

        return $vec;
    }

    
    // FILTRAR RUTAS POR USUARIO
    
    public function filtrar($id_usuario) {

        $sql = "SELECT r.id_ruta, r.nombre_ruta
                FROM usuario_ruta ur
                INNER JOIN rutas r ON r.id_ruta = ur.id_ruta
                WHERE ur.id_usuario = :id_usuario";

        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
