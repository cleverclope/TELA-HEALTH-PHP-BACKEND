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
                    $params['school_code'] = $_POST['school_code'] =
                        $this->get_school_code(explode(trim($_POST['school_name']), ' '), 0);
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
                                from schools order by school_name");
                $statement->execute();
                $schools = $statement->fetchAll();

                $statement = $this->connection->prepare("select student_id from students where school_id = :school_id limit 1");
                foreach ($schools as $index => $school) {
                    $statement->execute(['school_id' => $school['school_id']]);
                    $schools[$index]['population'] = $statement->rowCount();
                }

                return server_response(1, 'Schools', ['schools' => $schools]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function login_school() {
            try {
                $statement = $this->connection->prepare("select school_id, school_code, school_name, school_contacts, 
                                    section, district, sub_parish, parish, covid_facility, facility_contacts 
                                from schools where school_name = :school_name limit 1");
                $statement->execute();
                $school = $statement->fetch();
                if ($school) {
                    return server_response(1, 'Login Successful', ['school' => $school]);
                } else {
                    return server_response(2, 'Login not successful');
                }
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        private function get_school_code(string $school_code, int $count): string {
            $code = $count == 0 ? $school_code : "$school_code$count";
            $statement = $this->connection->prepare("select school_code from schools where school_code = :school_code limit 1");
            $statement->execute(['school_code' => $count == 0 ? $school_code : $code]);
            return !$statement->fetch() ? $code : $this->get_school_code($school_code, $count++);
        }
    }
