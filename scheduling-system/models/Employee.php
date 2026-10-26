<?php

namespace Models;

use Config\Database;

class Employee
{
    public static function allActive(): array
    {
        $stmt = Database::getInstance()->query('SELECT * FROM employees WHERE employment_status = "Active" ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Get active employees by domain
     */
    public static function getByDomain(string $domain): array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT * FROM employees WHERE employment_status = "Active" AND domain = ? ORDER BY name ASC'
        );
        $stmt->execute([$domain]);
        return $stmt->fetchAll();
    }

    /**
     * Get all unique domains from employees
     */
    public static function getDomains(): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT DISTINCT domain FROM employees WHERE domain IS NOT NULL AND domain != "" ORDER BY domain'
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function all(): array
    {
        $stmt = Database::getInstance()->query('SELECT * FROM employees ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static function findByEmployeeId(string $employeeId): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM employees WHERE employee_id = ?');
        $stmt->execute([$employeeId]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM employees WHERE email = ?');
        $stmt->execute([$email]);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static $importColumns = [
        'NIP'                 => 'employee_id',
        'Agent Name'          => 'name',
        'Agent Email'         => 'email',
        'Position'            => 'position',
        'LOB'                 => 'lob',
        'Email Team Leader'   => 'team_leader_email',
        'Operational Manager' => 'operational_mgr',
        'Gender'              => 'gender',
        'Domain'              => 'domain',
        'Compo Month'         => 'compo_month',
        'Year'                => 'compo_year',
    ];

    public static function create(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO employees (employee_id,name,email,gender,position,lob,team_leader,team_leader_email,operational_mgr,domain,compo_month,compo_year,file_name,employment_status,join_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        return $stmt->execute([
            $data['employee_id'],$data['name'],$data['email'],$data['gender'],
            $data['position']??null,$data['lob']??null,$data['team_leader']??null,
            $data['team_leader_email']??null,$data['operational_mgr']??null,$data['domain']??null,
            $data['compo_month']??null,$data['compo_year']??null,$data['file_name']??null,
            $data['employment_status']??'Active',$data['join_date']??null
        ]);
    }

    public static function bulkImport(array $rows): array
    {
        $db = Database::getInstance();
        $created = 0;
        $errors = [];
        try {
            $db->beginTransaction();
            $composToReplace = [];
            foreach ($rows as $row) {
                $m = $row['compo_month']??''; $y = $row['compo_year']??'';
                if ($m && $y) $composToReplace[$m.'|'.$y] = ['month'=>$m,'year'=>$y];
            }
            foreach ($composToReplace as $c) {
                $db->prepare('DELETE FROM employees WHERE compo_month=? AND compo_year=?')->execute([$c['month'],$c['year']]);
            }
            $stmt = $db->prepare('INSERT INTO employees (employee_id,name,email,gender,position,lob,team_leader_email,operational_mgr,domain,compo_month,compo_year,file_name,employment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($rows as $i => $row) {
                if (empty($row['employee_id']) && empty($row['name'])) continue;
                $fn = null;
                if (!empty($row['compo_month']) && !empty($row['compo_year'])) $fn = 'File_Compo_'.$row['compo_month'].'_'.$row['compo_year'];
                try {
                    $stmt->execute([$row['employee_id'],$row['name'],$row['email']??'',$row['gender']??'Male',$row['position']??null,$row['lob']??null,$row['team_leader_email']??null,$row['operational_mgr']??null,$row['domain']??null,$row['compo_month']??null,$row['compo_year']??null,$fn,'Active']);
                    $created++;
                } catch (\PDOException $e) { $errors[] = "Row ".($i+1).": ".$e->getMessage(); }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            return ['success'=>false,'created'=>$created,'errors'=>[$e->getMessage()]];
        }
        return ['success'=>true,'created'=>$created,'errors'=>$errors];
    }

    public static function update(int $id, array $data): bool
    {
        $stmt = Database::getInstance()->prepare('UPDATE employees SET employee_id=?,name=?,email=?,gender=?,position=?,lob=?,team_leader=?,team_leader_email=?,operational_mgr=?,domain=?,compo_month=?,compo_year=?,file_name=?,employment_status=?,join_date=? WHERE id=?');
        return $stmt->execute([
            $data['employee_id'],$data['name'],$data['email'],$data['gender'],
            $data['position']??null,$data['lob']??null,$data['team_leader']??null,
            $data['team_leader_email']??null,$data['operational_mgr']??null,$data['domain']??null,
            $data['compo_month']??null,$data['compo_year']??null,$data['file_name']??null,
            $data['employment_status']??'Active',$data['join_date']??null,$id
        ]);
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getInstance()->prepare('DELETE FROM employees WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public static function getTeamLeaders(): array
    {
        $stmt = Database::getInstance()->query('SELECT DISTINCT team_leader FROM employees WHERE team_leader IS NOT NULL AND team_leader != "" ORDER BY team_leader');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function getByTeamLeader(string $teamLeader): array
    {
        $stmt = Database::getInstance()->prepare('SELECT * FROM employees WHERE team_leader = ? AND employment_status = "Active" ORDER BY name');
        $stmt->execute([$teamLeader]);
        return $stmt->fetchAll();
    }

    public static function getFemale(): array
    {
        $stmt = Database::getInstance()->query('SELECT * FROM employees WHERE gender = "Female" AND employment_status = "Active" ORDER BY name');
        return $stmt->fetchAll();
    }
}
