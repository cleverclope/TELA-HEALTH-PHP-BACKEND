<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    class Students {
        private ?PDO $connection;

        public function __construct() {
            $this->connection = connect_database();
        }

        public function save_student() {
            try {
                $this->connection->beginTransaction();
                $params = [
                    'school_id'         => $_POST['school_id'],
                    'full_name'         => $_POST['full_name'],
                    'caretaker_name'    => $_POST['caretaker_name'],
                    'caretaker_contact' => $_POST['caretaker_contact'],
                    'date_of_birth'     => $_POST['date_of_birth'],
                    'gender'            => $_POST['gender']
                ];
                if ($_POST['student_id'] == 0) {
                    $statement = $this->connection->prepare("insert into students (school_id, student_no, full_name, caretaker_name, 
                                            caretaker_contact, date_of_birth, gender) values (:school_id, null, :full_name, 
                                        :caretaker_name, :caretaker_contact, :date_of_birth, :gender)");
                } else {
                    $statement = $this->connection->prepare("update students set school_id = :school_id, full_name = :full_name, 
                                            caretaker_name = :caretaker_name, caretaker_contact = :caretaker_contact, 
                                            date_of_birth = :date_of_birth, gender = :gender 
                                        where student_id = :student_id");
                    $params['student_id'] = $_POST['student_id'];
                }
                $statement->execute($params);
                $output['student_id'] = $_POST['student_id'] == 0 ? $this->connection->lastInsertId() : $_POST['student_id'];

                if ($_POST['student_id'] == 0) {
                    $statement = $this->connection->prepare("select school_code from schools where school_id = :school_id limit 1");
                    $statement->execute(['school_id' => $_POST['school_id']]);
                    $school_code = ($statement->fetch())['school_code'];

                    $statement = $this->connection->prepare("update students set student_no = :student_no where school_id = :school_id");
                    $output['student_no'] = $school_code . str_pad("{$output['student_id']}", 4, "0", STR_PAD_LEFT);
                    $statement->execute(['student_no' => $output['student_no']]);
                }

                init_path("utils/files/avatars");
                if (isset($_FILES["avatar"])) {
                    $file_extension = strtolower(pathinfo(basename($_FILES["avatar"]["name"]), PATHINFO_EXTENSION));
                    $output['file_name'] = time() . "{$output['student_id']}.$file_extension";

                    if (!move_uploaded_file($_FILES["avatar"]["tmp_name"], "utils/files/avatars/{$output['file_name']}")) {
                        $this->connection->rollBack();
                        return server_response(2, 'Could not save student avatar');
                    }

                    $statement = $this->connection->prepare("select avatar from students 
                                where student_id = :student_id and avatar <> '' and avatar is not null limit 1");
                    $statement->execute(['student_id' => $output['student_id']]);
                    $avatar = $statement->fetch();

                    if ($avatar) {
                        unlink("utils/files/avatars/{$avatar['avatar']}");
                    }

                    $statement = $this->connection->prepare("update students set avatar = :avatar where student_id = :student_id");
                    $statement->execute(['student_id' => $output['student_id'], 'avatar' => $output['file_name']]);
                }

                return server_response(1, 'Student saved successfully', ['student' => $output]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_students() {
            try {
                $statement = $this->connection->prepare("select student_id, s.school_id, student_no, full_name, caretaker_name, 
                                        caretaker_contact, date_of_birth, gender, school_name 
                                    from students inner join schools s on students.school_id = s.school_id
                                    order by full_name");
                $statement->execute();
                $students = $statement->fetchAll();
                return server_response(1, 'Students', ['students' => $students]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }
    }
