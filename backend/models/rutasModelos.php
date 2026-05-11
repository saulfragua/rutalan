<?php 
class Rutas {
    private $conexion;

    public function __construct($conexion) {
        $this->conexion = $conexion;
    }

    // método consultar
    public function consultar(){
        $sql = "SELECT * FROM rutas";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // método eliminar (eliminado lógico recomendado)
    public function eliminar($id){
        $sql = "DELETE FROM rutas WHERE id_ruta = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Ruta eliminada correctamente";
        $vec['mensaje'] = "La ruta ha sido eliminada de la base de datos";
        return $vec;
    }


    // método insertar
    public function insertar($params){
        $sql = "INSERT INTO rutas (nombre_ruta, activo)
                VALUES (:nombre_ruta, 1)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':nombre_ruta', $params['nombre'], PDO::PARAM_STR);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Ruta insertada correctamente";
        $vec['mensaje'] = "La ruta ha sido agregada a la base de datos";
        return $vec;
    }

    // método editar
    public function editar($id, $params){
        $sql = "UPDATE rutas 
                SET nombre_ruta = :nombre_ruta
                WHERE id_ruta = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindParam(':nombre_ruta', $params['nombre'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $vec = [];
        $vec['resultado'] = "Ruta editada correctamente";
        $vec['mensaje'] = "La ruta ha sido actualizada en la base de datos";
        return $vec;
    }

    // método filtrar
    public function filtrar($valor){
        $sql = "SELECT * FROM rutas WHERE nombre_ruta LIKE :valor";
        $stmt = $this->conexion->prepare($sql);
        $like = "%$valor%";
        $stmt->bindParam(':valor', $like, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // método cambiarEstado o inactivar activar
    public function cambiarEstado($id, $estado){
    $sql = "UPDATE rutas SET activo = :estado WHERE id_ruta = :id";
    $stmt = $this->conexion->prepare($sql);
    $stmt->bindParam(':estado', $estado, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $vec = [];
    $vec['resultado'] = "OK";
    $vec['mensaje'] = $estado == 1
        ? "Ruta activada correctamente"
        : "Ruta inactivada correctamente";

    return $vec;
}

}
?>
