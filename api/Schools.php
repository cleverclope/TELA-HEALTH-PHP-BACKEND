<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    class Schools {
        private ?PDO $connection;

        public function __construct() {
            $this->connection = connect_database();
        }

        public function save_school() {
            try {
                $params = [
                    'school_name'       => trim($_POST['school_name']),
                    'school_contacts'   => $_POST['school_contacts'],
                    'section'           => $_POST['section'],
                    'district'          => $_POST['district'],
                    'sub_parish'        => $_POST['sub_parish'],
                    'parish'            => $_POST['parish'],
                    'covid_facility'    => $_POST['covid_facility'],
                    'facility_contacts' => $_POST['facility_contacts']
                ];

                if ($_POST['school_id'] == 0) {
                    $statement = $this->connection->prepare("insert into schools (school_code, school_name, school_contacts, 
                                        section, district, sub_parish, parish, covid_facility, facility_contacts) values 
                                        (:school_code, :school_name, :school_contacts, :section, :district, :sub_parish, :parish, 
                                         :covid_facility, :facility_contacts)");
                    $school_code = "";
                    foreach (explode(' ', "{$_POST['school_name']}") as $value) {
                        $school_code .= substr($value, 0, 1);
                    }
                    $params['school_code'] = $_POST['school_code'] = $this->get_school_code($school_code, 1);
                } else {
                    $statement = $this->connection->prepare("update schools set school_name = :school_name, 
                                            school_contacts = :school_contacts, section = :section, district = :district, 
                                            sub_parish = :sub_parish, parish = :parish, covid_facility = :covid_facility, 
                                            facility_contacts = :facility_contacts
                                           where school_id = :school_id");
                    $params['school_id'] = $_POST['school_id'];
                }
                $statement->execute($params);
                $school_id = $_POST['school_id'] == 0 ? $this->connection->lastInsertId() : $_POST['school_id'];

                return server_response(1, 'School Saved',
                    ['school_id' => $school_id, 'school_code' => $_POST['school_code']]
                );
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_schools() {
            try {
                $statement = $this->connection->prepare("select school_id, school_code, school_name, school_contacts, 
                                    section, district, sub_parish, parish, covid_facility, facility_contacts 
                                from schools where school_name like :school_name order by school_name");
                $statement->execute(['school_name' => "%{$_GET['school_name']}%"]);
                $schools = $statement->fetchAll();

                $statement_classes = $this->connection->prepare("Select sc.class_id, streams, class_name
                                from school_classes sc inner join user_classes uc on sc.class_id = uc.class_id where school_id = :school_id");
                $statement = $this->connection->prepare("select user_id from users_students where school_id = :school_id limit 1");

                foreach ($schools as $index => $school) {
                    $statement->execute(['school_id' => $school['school_id']]);
                    $schools[$index]['population'] = $statement->rowCount();

                    $schools[$index]['classes'] = [];
                    $statement_classes->execute(['school_id' => $school['school_id']]);
                    foreach ($statement_classes->fetchAll() as $class) {
                        $class['streams'] = json_decode($class['streams'], true);
                        $schools[$index]['classes'][] = $class;
                    }
                }

                $statement = $this->connection->prepare("select class_id, class_name from user_classes order by class_id");
                $statement->execute();
                $classes = $statement->fetchAll();

                return server_response(1, 'Schools', ['schools' => $schools, 'classes' => $classes]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function save_classes() {
            try {
                $this->connection->beginTransaction();

                foreach (json_decode($_POST['classes'], true) as $class) {
                    $statement = $this->connection->prepare("select streams from school_classes 
                                where school_id = :school_id and class_id = :class_id limit 1");
                    $statement->execute(['school_id' => $_POST['school_id'], 'class_id' => $class['class_id']]);

                    if ($statement->fetch()) {
                        $statement = $this->connection->prepare("update school_classes set streams = :streams where
                                                   school_id = :school_id and class_id = :class_id");
                    } else {
                        $statement = $this->connection->prepare("insert into school_classes (school_id, class_id, streams) 
                                        VALUES (:school_id, :class_id, :streams)");
                    }
                    $statement->execute([
                            'streams'   => json_encode($class['streams']),
                            'school_id' => $_POST['school_id'],
                            'class_id'  => $class['class_id']]
                    );
                }

                $statement = $this->connection->prepare("Select sc.class_id, streams, class_name
                                from school_classes sc inner join user_classes uc on sc.class_id = uc.class_id where school_id = :school_id");
                $statement->execute(['school_id' => $_POST['school_id']]);

                $classes = [];
                foreach ($statement->fetchAll() as $class) {
                    $class['streams'] = json_decode($class['streams'], true);
                    $classes[] = $class;
                }

                $this->connection->commit();
                return server_response(1, 'Classes saved successfully', ['classes' => $classes]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function login_school() {
            try {
                $statement = $this->connection->prepare("select school_id, school_code, school_name, school_contacts, 
                                    section, district, sub_parish, parish, covid_facility, facility_contacts 
                                from schools where school_name = :school_name limit 1");
                $statement->execute(['school_name' => $_POST['school_name']]);
                $school = $statement->fetch();
                if ($school) {
                    $school['school_contacts'] = explode(",", $school['school_contacts']);
                    $school['facility_contacts'] = explode(",", $school['facility_contacts']);
                    return server_response(1, 'Login Successful', ['school' => $school]);
                } else {
                    return server_response(2, 'Login not successful');
                }
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function initialise_data() {
            $statement = $this->connection->prepare("select class_id, class_name from user_classes order by class_id");
            $statement->execute();
            $classes = $statement->fetchAll();
            return server_response(1, 'Initial Data', ['classes' => $classes]);
        }

        private function get_school_code(string $school_code, int $count): string {
            $code = "$school_code-" . str_pad("$count", 2, "0", STR_PAD_LEFT);
            $statement = $this->connection->prepare("select school_code from schools where school_code = :school_code");
            $statement->execute(['school_code' => $code]);
            return $statement->rowCount() == 0 ? $code : $this->get_school_code($school_code, $count + 1);
        }
    }
