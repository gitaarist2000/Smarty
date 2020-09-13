<?php


const USERS_FILE = 'users.json';
const PRODUCTS_FILE = 'products.json';
const CATEGORIES_FILE = 'categories.json';
const ORDERS_FILE = 'orders.json';
const PRODUCT_IMAGES_FOLDER = 'product_images';

function loginEndpoint()
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        global $smarty;

        $smarty->display('login.tpl');

        return;
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    makeLogin($email, $password);
}

function makeLogin(string $email, string $password)
{
    $email = strtolower($email);

    if (strpos($email, '@') === false) {
        header("Location: /?action=login&error=Email should be correct");

        return;
    }

    if (strlen($password) < 8) {
        header("Location: /?action=login&error=Password should be at least 8 characters");

        return;
    }

    $users = readFromFile(USERS_FILE);

    foreach ($users as $user) {
        if ($email === $user['email']) {
            if (!password_verify($password, $user['password'])) {
                header("Location: /?action=login&error=Credentials are incorrect or user does not exist");

                return;
            }

            //EMAIL FOUND AND PASSWORD CORRECT
            header("Location: /");

            $_SESSION['user'] = $user;
            return;
        }
    }

    header("Location: /?action=login&error=Credentials are incorrect or user does not exist");
}

function registerEndpoint()
{

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        global $smarty;

        $smarty->display('register.tpl');

        return;
    }

    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    makeRegistration($email, $password);
}

function makeRegistration(string $email, string $password)
{
    $email = strtolower($email);

    if (strpos($email, '@') === false) {
        header("Location: /?action=register&error=Email should be correct");

        return;
    }

    if (strlen($password) < 8) {
        header("Location: /?action=register&error=Password should be at least 8 characters");

        return;
    }

    $users = readFromFile(USERS_FILE);

    foreach ($users as $user) {
        if ($email === $user['email']) {
            header("Location: /?action=register&error=User already exists");
            return;
        }
    }

    $users[] = $user = [
        'id' => md5(time() . rand(1,1000000)),
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'is_admin' => 0,
        'created_at' => date("d-m-Y")
    ];

    if (!writeFile(USERS_FILE, $users)) {
        header("Location: /?action=register&error=Server error. Not able to create user");

        return;
    }

    $_SESSION['user'] = $user;

    header("Location: /");
}

function cartEndpoint()
{
    global $smarty;

    $smarty->display('cart.tpl');
}

function logoutEndpoint()
{
    if (isset($_SESSION['user'])) {
        unset($_SESSION['user']);
    }

    header("Location: /");
}

function mainPageEndpoint()
{
    global $smarty;

    $smarty->assign('categories', getCategoriesList());
    $smarty->display('index.tpl');
}

function adminUsersEndpoint()
{
    global $smarty;

    $users = readFromFile(USERS_FILE);

    $smarty->assign('users', $users);

    $smarty->display('admin/users.tpl');
}

function adminCategoriesEndpoint()
{

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        global $smarty;

        $smarty->assign('categories', getCategoriesList());

        $smarty->display('admin/categories.tpl');

        return;
    }

    $categoryName = $_POST['category_name'] ?? '';
    $categoryOrder = (int) $_POST['category_order'] ?? 0;

    createCategory($categoryName, $categoryOrder);

}

function getCategoriesList()
{
    $categories = readFromFile(CATEGORIES_FILE, []);

    $len = count($categories) - 1;

    for ($j = 0; $j < $len; $j++) {
        for($i = 0; $i < $len; $i++) {
            if ($categories[$i]['order'] > $categories[$i + 1]['order']) {
                $tmp = $categories[$i];// $tmp = 8
                $categories[$i] = $categories[$i + 1]; // $arr[0] = 4
                $categories[$i + 1] = $tmp; // $arr[1] = $tmp = 8;
            }
        }
    }

    return $categories;
}

function createCategory(string $categoryName, int $categoryOrder)
{

    if (strlen($categoryName) < 3) {
        header("Location: /?action=adminCategories&error=Category name should be at least 3 symbols");
        return;
    }

    $categories = readFromFile(CATEGORIES_FILE);

    $categories[] = [
        'id' => md5(time() . rand(1,10000000)),
        'name' => $categoryName,
        'order' => $categoryOrder
    ];

    writeFile(CATEGORIES_FILE, $categories);

    header("Location: /?action=adminCategories&message=Category <b>$categoryName</b> created successfully!");
}


function adminProductsEndpoint()
{
    global $smarty;

    $smarty->assign('categories', getCategoriesList());
    $smarty->assign('products', showProductsList());
    $smarty->display('admin/products.tpl');




}

function adminOrdersEndpoint()
{
    global $smarty;

    $smarty->display('admin/orders.tpl');
}

function ordersEndpoint()
{
    global $smarty;

    $smarty->display('orders.tpl');
}

function writeFile(string $fileName, $data): bool
{
    return (bool) file_put_contents($fileName, json_encode($data));
}

function readFromFile(string $fileName, $default = [])
{
    if (!file_exists($fileName)) {
        return $default;
    }

    $json = file_get_contents($fileName);

    return json_decode($json, true);
}

function adminUpdateCategoryEndpoint()
{
    $name = $_POST['name'] ?? '';
    $order = $_POST['order'] ?? 0;
    $id = $_POST['id'] ?? 0; //123

    if (strlen($name) < 3) {
        header("Location: /?action=adminCategories&error=Category name should be at least 3 symbols length!");
        return;
    }

    if ($id === 0) {
        header("Location: /?action=adminCategories&error=Undefined category to update. Please send us ID");
        return;
    }

    if (!is_numeric($order)) {
        header("Location: /?action=adminCategories&error=Order should be numeric");
        return;
    }

    $categories = getCategoriesList();

    $updated = false;

    foreach ($categories as &$category) {
        if ($category['id'] === $id) {
            $category['name'] = $name;
            $category['order'] = $order;

            $updated = true;
        }
    }

    if (!$updated) {
        header("Location: /?action=adminCategories&error=The category with id $id does not exists");
        return;
    }

    writeFile(CATEGORIES_FILE, $categories);

    header("Location: /?action=adminCategories&message=The category with id=$id has been updated!");

}

function adminRemoveCategoryEndpoint()
{
    $categoryId = $_GET['categoryId'] ?? '';

    if (strlen($categoryId) != 32) {
        header("Location: /?action=adminCategories&error=Wrong ID given");
    }



    for($i = 0; $i < $len; $i++) {
        if ($categories[$i]['id'] === $categoryId) {
            unset($categories[$i]);
            $deleted = true;
            break;
        }
    }

    if (!$deleted) {
        header("Location: /?action=adminCategories&error=404 not found! The category you want to remove does not exist!");
    }

    writeFile(CATEGORIES_FILE, array_values($categories));

    header("Location: /?action=adminCategories&message=The category with id=$categoryId has been removed!");
}

function checkUserRole()
{

    if (!($_SESSION['user'] ?? false)) {
        return;
    }

    $userMail = $_SESSION['user']['email'];
    $userRole = $_SESSION['user']['is_admin'];

    foreach (readFromFile(USERS_FILE) as $user) {
        if ($userMail === $user['email'] && $userRole !== $user['is_admin']) {
            logoutEndpoint();
        }
    }
}

function adminChangeRoleEndpoint()
{
    $id = $_POST['id'] ?? 0;
    $admin = $_POST['admin'] ?? null;

    if ($admin === null) {
        header("Location: /?action=adminUsers&error=You did not specify user role!");
        return;
    }

    if ($id === 0) {
        header("Location: /?action=adminUsers&error=You did not specify user ID!");
        return;
    }

    if ($_SESSION['user']['id'] === $id) {
        header("Location: /?action=adminUsers&error=You are not able to manage your own role!");
        return;
    }

    $admin = (int) $admin;

    $users = readFromFile(USERS_FILE);

    $updated = false;

    foreach ($users as &$user) {
        if ($user['id'] === $id) {
            $user['is_admin'] = $admin;
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        header("Location: /?action=adminUsers&error=User did not find!");
        return;
    }

    writeFile(USERS_FILE, $users);

    header("Location: /?action=adminUsers&message=You updated user role for user with ID $id!");
    return;
}

function adminAddProductEndpoint() {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? null;
    $image = $_FILES['image'] ?? '';
    $category_id = $_POST['category_id'] ?? '';

    //TODO ADD VALIDATIONS

    $products = readFromFile(PRODUCTS_FILE, []);

    $productPicture = null;

    if ($image && ($image['tmp_name'] ?? false)) {
        $imageExt = explode('.', $image['name']);
        $imageExt = $imageExt[count($imageExt) - 1];

        $productPicture = PRODUCT_IMAGES_FOLDER . '/' . md5(time()) . '.' . $imageExt;
        move_uploaded_file($image['tmp_name'], __DIR__ . 'functions.php/' . $productPicture);
    }

    $product = [
        'id' => md5(time() . rand(1,100000)),
        'name' => $name,
        'price' => $price,
        'image' => $productPicture,
        'category_id' => $category_id
    ];

    $products[] = $product;

    writeFile(PRODUCTS_FILE, $products);

    header("Location: /?action=adminProducts");
}

function adminRemoveUsersEndpoint()
{
    $userId = $_GET['userId'];

    if($userId===null)return;

    $user =  readFromFile(USERS_FILE);

    $len = count($user);

    for ($i = 0; $i < $len; $i++)
        if ($user[$i]['id'] === $userId) {
            unset($user[$i]);

            writeFile(USERS_FILE, $user);
            header("Location: /?action=adminUsers&message=You DELIT USERS");
            return;
        }
}
function showProductsList()
{


    $products = readFromFile(PRODUCTS_FILE, []);
    return $products;


}
