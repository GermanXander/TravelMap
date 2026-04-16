<?php
/**
 * PasswordShare Model
 *
 * Gestiona las contraseñas para compartir viajes
 */

class PasswordShare {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crea una nueva contraseña de compartir
     */
    public function create($password, $trips, $expiresAt, $description = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO password_shares (password, trips, expires_at, description, active)
                VALUES (?, ?, ?, ?, TRUE)
            ");
            return $stmt->execute([$password, $trips, $expiresAt, $description]);
        } catch (Exception $e) {
            // Fallback for installations without the description column.
            if (strpos($e->getMessage(), 'Unknown column') !== false || strpos($e->getMessage(), '1054') !== false) {
                try {
                    $stmt = $this->db->prepare("
                        INSERT INTO password_shares (password, trips, expires_at, active)
                        VALUES (?, ?, ?, TRUE)
                    ");
                    return $stmt->execute([$password, $trips, $expiresAt]);
                } catch (Exception $fallback) {
                    error_log("Error creating password share fallback: " . $fallback->getMessage());
                    return false;
                }
            }

            error_log("Error creating password share: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si una contraseña es válida y retorna los viajes permitidos
     */
    public function validatePassword($password) {
        try {
            $stmt = $this->db->prepare("
                SELECT trips, expires_at, active
                FROM password_shares
                WHERE password = ? AND active = TRUE
            ");
            $stmt->execute([$password]);
            $row = $stmt->fetch();

            if (!$row) {
                return false;
            }

            // Verificar expiración: nulo significa que nunca expira
            if ($row['expires_at'] !== null) {
                $expiresAt = strtotime($row['expires_at']);
                if (time() > $expiresAt) {
                    return false;
                }
            }

            return $row['trips'];
        } catch (Exception $e) {
            error_log("Error validating password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todas las contraseñas activas y no expiradas
     */
    public function getActive() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, password, trips, description, created_at, expires_at, active
                FROM password_shares
                WHERE active = TRUE AND (expires_at IS NULL OR expires_at >= CURDATE())
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting active password shares: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT id, password, trips, created_at, expires_at, active
                    FROM password_shares
                    WHERE active = TRUE AND (expires_at IS NULL OR expires_at >= CURDATE())
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll();
                foreach ($rows as &$row) {
                    $row['description'] = '';
                }
                return $rows;
            } catch (Exception $fallback) {
                error_log("Fallback error getting active password shares: " . $fallback->getMessage());
                return [];
            }
        }
    }

    /**
     * Activa/desactiva una contraseña
     */
    public function toggleActive($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE password_shares
                SET active = NOT active
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Error toggling password share active status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si una contraseña ya existe
     */
    public function passwordExists($password) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM password_shares WHERE password = ?");
            $stmt->execute([$password]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error checking password existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Genera una contraseña aleatoria única
     */
    public function generateUniquePassword($length = 10) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= $chars[rand(0, strlen($chars) - 1)];
            }
        } while ($this->passwordExists($password));

        return $password;
    }

    /**
     * Obtiene los nombres de los viajes para mostrar en la lista
     */
    public function getTripNames($trips) {
        if ($trips === '*') {
            return '*';
        }

        if (empty($trips)) {
            return '-';
        }

        try {
            $ids = explode(',', $trips);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $this->db->prepare("
                SELECT title FROM trips
                WHERE id IN ($placeholders)
                ORDER BY title ASC
            ");
            $stmt->execute($ids);
            $titles = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return implode(', ', $titles);
        } catch (Exception $e) {
            error_log("Error getting trip names: " . $e->getMessage());
            return $trips; // Fallback to showing IDs
        }
    }
}