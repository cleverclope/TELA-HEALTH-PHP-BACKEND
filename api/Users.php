<?php

    namespace tenna_health;

    use Exception;
    use PDO;

    class Users {
        private ?PDO $connection;

        public function __construct() {
            $this->connection = connect_database();
        }

        private function save_user(string $user_type): array {
            try {
                $params = [
                    'last_name'     => $_POST['last_name'],
                    'first_name'    => $_POST['first_name'],
                    'nin_number'    => $_POST['nin_number'],
                    'face_gender'   => $_POST['face_gender'],
                    'date_of_birth' => $_POST['date_of_birth']
                ];

                if ($_POST['user_id'] == 0) {
                    $statement = $this->connection->prepare("insert into users (last_name, first_name, face_gender, date_of_birth, 
                                            nin_number, user_type) values (:last_name, :first_name, :face_gender, :date_of_birth, 
                                            :nin_number, :user_type)");
                    $params['user_type'] = $user_type;
                } else {
                    $statement = $this->connection->prepare("update users set last_name = :last_name, first_name = :first_name, 
                                             nin_number = :nin_number, face_gender = :face_gender, date_of_birth = :date_of_birth 
                                        where user_id = :user_id");
                    $params['user_id'] = $_POST['user_id'];
                }
                $statement->execute($params);
                $user_id = $_POST['user_id'] == 0 ? $this->connection->lastInsertId() : $_POST['user_id'];
                return ['code' => 1, 'user_id' => $user_id];
            } catch (Exception $exception) {
                server_error($exception);
                return ['code' => 3, 'msg' => $exception->getMessage()];
            }
        }

        /*student information*/
        public function save_student() {
            try {
                $this->connection->beginTransaction();

                /*saving the user information*/
                $user = $this->save_user('Student');
                if ($user['code'] != 1) {
                    return server_response($user['code'], $user['msg']);
                }

                $params = [
                    'user_id'           => $user['user_id'],
                    'school_id'         => $_POST['school_id'],
                    'caretaker_name'    => $_POST['caretaker_name'],
                    'caretaker_contact' => $_POST['caretaker_contact'],
                    'class_id'          => $_POST['class_id'],
                    'class_stream'      => $_POST['class_stream']
                ];
                if ($_POST['user_id'] == 0) {
                    $statement = $this->connection->prepare("insert into users_students (user_id, school_id, student_no, caretaker_name,
                                        caretaker_contact, class_id, class_stream) values (:user_id, :school_id, null, 
                                        :caretaker_name, :caretaker_contact, :class_id, :class_stream)");
                } else {
                    $statement = $this->connection->prepare("update users_students set school_id = :school_id, class_stream = :class_stream,
                                            caretaker_name = :caretaker_name, caretaker_contact = :caretaker_contact
                                        where user_id = :user_id");
                    $params['user_id'] = $_POST['user_id'];
                }
                $statement->execute($params);

                if ($_POST['user_id'] == 0) {
                    $statement = $this->connection->prepare("select school_code from schools where school_id = :school_id limit 1");
                    $statement->execute(['school_id' => $_POST['school_id']]);
                    $school_code = ($statement->fetch())['school_code'];

                    $statement = $this->connection->prepare("update users_students set student_no = :student_no where user_id = :user_id");
                    $student_no = $school_code . str_pad("{$user['user_id']}", 4, "0", STR_PAD_LEFT);
                    $statement->execute(['student_no' => $student_no, 'user_id' => $user['user_id']]);
                }

                /*retrieving the student data*/
                $statement = $this->connection->prepare("select student_no, user_id from users_students where user_id = :user_id limit 1");
                $statement->execute(['user_id' => $user['user_id']]);
                $student = $statement->fetch();

                $this->connection->commit();
                return server_response(1, 'Student saved successfully', $student);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_students() {
            try {
                $params = [];
                $where = "";
                if (isset($_GET['school_id']) && $_GET['school_id'] > 0) {
                    $params['school_id'] = $_GET['school_id'];
                    $where = "and s.school_id = :school_id";
                }
                if (isset($_GET['class_id']) && $_GET['class_id'] > 0) {
                    $params['class_id'] = $_GET['class_id'];
                    $where = "and uc.class_id = :class_id";
                }

                if (isset($_GET['source']) && $_GET['source'] == 'admin') {
                    $statement = $this->connection->prepare("select u.user_id, s.school_id, s.school_name, student_no, caretaker_name, 
                                        caretaker_contact, last_name, first_name, nin_number, face_gender, date_of_birth, uc.class_id, 
                                        uc.class_name, class_stream
                                    from users_students 
                                        inner join users u on users_students.user_id = u.user_id
                                        inner join user_classes uc on users_students.class_id = uc.class_id
                                        inner join schools s on users_students.school_id = s.school_id
                                    where u.user_id >= 1 $where order by last_name, first_name, school_name, class_name");
                    $statement->execute($params);
                    $students = $statement->fetchAll();

                    $statement = $this->connection->prepare("select schools.school_id, school_name from schools order by school_name");
                    $statement->execute();
                    $schools = $statement->fetchAll();

                    $statement = $this->connection->prepare("select streams, sc.class_id, class_name
                                from school_classes sc inner join user_classes uc on sc.class_id = uc.class_id where school_id = :school_id");
                    $school_list = [];
                    foreach ($schools as $index => $school) {
                        $statement->execute(['school_id' => $school['school_id']]);
                        $classes = [];
                        foreach ($statement->fetchAll() as $class) {
                            $class['streams'] = json_decode($class['streams'], true);
                            $classes[] = $class;
                        }
                        $schools[$index]['classes'] = $classes;
                        if (count($classes) > 0) {
                            $school_list[] = $schools[$index];
                        }
                    }
                } else {
                    $statement = $this->connection->prepare("select u.user_id, us.school_id, school_name, student_no, caretaker_name,
                                            caretaker_contact, uc.class_id, uc.class_name, class_stream
                                        from users_students us
                                            inner join users u on us.user_id = u.user_id   
                                            inner join user_classes uc on us.class_id = uc.class_id
                                            inner join schools s on us.school_id = s.school_id
                                        where us.user_id >= 1 $where order by last_name, first_name, school_name, class_name");

                    $statement->execute($params);
                    $students = $statement->fetchAll();
                    $users = $this->get_users($students);
                }

                return server_response(1, 'Students', ['students' => $students, 'schools' => $school_list ?? [], 'users' => $users ?? []]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        /*staff information*/
        public function save_staff() {
            try {
                $this->connection->beginTransaction();

                /*saving the user information*/
                $user = $this->save_user('Staff');
                if ($user['code'] != 1) {
                    return server_response($user['code'], $user['msg']);
                }

                $params = [
                    'user_id'        => $user['user_id'],
                    'school_id'      => $_POST['school_id'],
                    'mobile_contact' => $_POST['mobile_contact'],
                    'email_address'  => $_POST['email_address']
                ];

                if ($_POST['user_id'] == 0) {
                    $statement = $this->connection->prepare("insert into users_staff (user_id, school_id, mobile_contact,
                                        email_address) VALUES (:user_id, :school_id, :mobile_contact, :email_address)");
                } else {
                    $statement = $this->connection->prepare("update users_staff set school_id = :school_id, mobile_contact = :mobile_contact,
                                        email_address = :email_address where user_id = :user_id");
                }
                $statement->execute($params);

                $statement = $this->connection->prepare("select u.user_id, s.school_id, mobile_contact, email_address,
                                    last_name, face_id, face_path, face_feature, face_age, face_gender, face_head, date_of_birth, school_name
                                from users_staff
                                    inner join users u on users_staff.user_id = u.user_id
                                    inner join schools s on users_staff.school_id = s.school_id
                                where u.user_id = :user_id");
                $statement->execute(['user_id' => $user['user_id']]);

                $statement->execute(['user_id' => $user['user_id']]);
                $staff = $statement->fetch();

                $this->connection->commit();
                return server_response(1, 'Staff saved successfully', ['staff' => $staff]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function get_staffs() {
            try {
                $params = [];
                $where = "";

                if (isset($_GET['school_id']) && $_GET['school_id'] > 0) {
                    $params['school_id'] = $_GET['school_id'];
                    $where = "and s.school_id = :school_id";
                }

                if (isset($_GET['source']) && $_GET['source'] == 'admin') {
                    $statement = $this->connection->prepare("select u.user_id, s.school_id, mobile_contact, email_address,
                                    last_name, first_name, nin_number, face_gender, date_of_birth, school_name
                                from users_staff
                                    inner join users u on users_staff.user_id = u.user_id
                                    inner join schools s on users_staff.school_id = s.school_id
                                where u.user_id > 0 $where order by school_name, last_name, first_name");
                    $statement->execute($params);
                    $staff = $statement->fetchAll();

                    $statement = $this->connection->prepare("select school_id, school_name from schools order by school_name");
                    $statement->execute();
                    $schools = $statement->fetchAll();

                } else {
                    $statement = $this->connection->prepare("select u.user_id, s.school_id, mobile_contact, email_address, school_name
                                from users_staff
                                    inner join schools s on users_staff.school_id = s.school_id
                                    inner join users u on users_staff.user_id = u.user_id
                                where u.user_id > 0 $where");
                    $statement->execute($params);
                    $staff = $statement->fetchAll();

                    $users = $this->get_users($staff);
                }

                return server_response(1, 'Staff List', ['staff' => $staff, 'schools' => $schools ?? [], 'users' => $users ?? []]);
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        public function attach_photo() {
            try {
                if (isset($_FILES["face_path"])) {
                    init_path("utils/files/avatars");
                    $file_extension = strtolower(pathinfo(basename($_FILES["face_path"]["name"]), PATHINFO_EXTENSION));
                    $file_name = time() . "{$_POST['user_id']}.$file_extension";

                    if (!move_uploaded_file($_FILES["face_path"]["tmp_name"], "utils/files/avatars/$file_name")) {
                        $this->connection->rollBack();
                        return ['code' => 2, 'msg' => 'Could not save user avatar'];
                    }

                    $statement = $this->connection->prepare("select face_path from users
                                where user_id = :user_id and face_path <> '' and face_path is not null limit 1");
                    $statement->execute(['user_id' => $_POST['user_id']]);
                    $avatar = $statement->fetch();

                    if ($avatar) {
                        unlink("utils/files/avatars/{$avatar['face_path']}");
                    }

                    $statement = $this->connection->prepare("update users set face_path = :face_path, face_id = :face_id,
                                            face_feature = :face_feature, face_age = :face_age, face_head = :face_head
                                        where user_id = :user_id");
                    $statement->execute([
                        'user_id'      => $_POST['user_id'],
                        'face_id'      => $_POST['face_id'],
                        'face_feature' => $_POST['face_feature'],
                        'face_age'     => $_POST['face_age'],
                        'face_head'    => $_POST['face_head'],
                        'face_path'    => $file_name,
                    ]);
                    return server_response(1, 'Face data attached successfully', ['face_path' => $file_name]);
                } else {
                    return server_response(2, 'No face file attached');
                }
            } catch (Exception $exception) {
                return server_error($exception);
            }
        }

        private function get_users(array $users) {
            if (count($users) == 0) {
                return [];
            }

            $ids = [];
            foreach ($users as $user) {
                $ids[] = $user['user_id'];
            }

            $ids = implode(', ', $ids);
            $statement = $this->connection->prepare("select user_id, user_type, first_name, last_name, face_gender, date_of_birth, 
                                    nin_number, face_id, face_age, face_path, face_feature, face_head 
                                from users where user_id in ($ids)");
            $statement->execute();
            return $statement->fetchAll();
        }
    }
