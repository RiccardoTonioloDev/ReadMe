<?php

namespace Pangine;
require_once(__DIR__ . "/../Utils/ErroriMigliori.php");
require_once(__DIR__ . "/../Utils/Database.php");
require_once(__DIR__ . "/utils/Exception500.php");
require_once(__DIR__ . "/utils/Validator.php");

use Pangine\utils\Validator;
use \Utils\Database;
use \Pangine\utils\Exception500;

define("status_UNREGISTERED", "UNREGISTERED");

class Pangine
{

    // renderers that require a specific field to be set
    private array $controlled_renderers_unpure = [];
    // renderers that don't require any specific field to be set
    private array $controlled_renderers_pure = [];

    private static string $status_UNREGISTERED = "UNREGISTERED";

    private static string $status_USER = "USER";
    private static string $status_ADMIN = "ADMIN";

    private static array $pages = [];

    public static function path() {
      return $panginepath = "/";
    }

    public static function redirect(string $url = ''): void
    {
      header('Location: ' . Pangine::path() . $url);
      exit();
    }

    public function __construct()
    {
        $this->try_session();
        self::$pages = array(
            "Chi siamo" => array(
                "path" => "Pages/chi_siamo.php",
                "privileges" => array(self::UNREGISTERED()),
                "show_in_navbar" => true,
            ),
            "Catalogo" => array(
                "path" => "Pages/catalogo.php",
                "privileges" => array(self::UNREGISTERED(), self::USER(), self::ADMIN()),
                "show_in_navbar" => true,
            ),
            "Accedi" => array(
                "path" => "Pages/accedi.php",
                "privileges" => array(self::UNREGISTERED()),
                "show_in_navbar" => true,
            ),
            "Registrati" => array(
                "path" => "Pages/registrati.php",
                "privileges" => array(self::UNREGISTERED()),
                "show_in_navbar" => true,
            ),
            "Home" => array(
                "path" => "Pages/index.php",
                "privileges" => array(self::UNREGISTERED(),self::USER(),self::ADMIN()),
                "show_in_navbar" => false,
            ),
            "Account" => array(
              "path" => "Pages/account.php",
              "privileges" => array(self::USER(), self::ADMIN()),
                "show_in_navbar" => true,

            ),
            "Libro" => array(
                "path" => "Pages/libro.php",
                "privileges" => array(self::UNREGISTERED(),self::USER(),self::ADMIN()),
                "show_in_navbar" => false,
            ),
            "Noleggia" => array(
                "path" => "Pages/loan.php",
                "privileges" => array(self::USER(),self::ADMIN()),
                "show_in_navbar" => false,
            ),
            "Nuovo Libro" => array(
                "path" => "Pages/crud_libro.php",
                "privileges" => array(self::ADMIN()),
                "show_in_navbar" => true,
            ),
            "Prestiti" => array(
                "path" => "Pages/prestiti.php",
                "privileges" => array(self::USER()),
                "show_in_navbar" => true,
            )
        );
    }

    public function execute(): void
    {
        try {
            foreach (self::$pages as $page) {
                if (str_contains($_SERVER['REQUEST_URI'], $page['path'])) {
                    if (count($page["privileges"]) && !in_array($_SESSION["user"]["status"], $page["privileges"])) {
                        		Pangine::redirect("Pages/403.php");
                    }
                }
            }
            foreach ($this->controlled_renderers_unpure as $renderer) {
                $renderer();
            }
            foreach ($this->controlled_renderers_pure as $renderer) {
                $renderer();
            }
            Pangine::redirect("Pages/404.php"); //If arrived here it means that no renderer was activated
        } catch (Exception500 $e) {
            $_SESSION["error500message"] = $e->getMessage();
            		Pangine::redirect("Pages/500.php");
        }
    }

    public static function UNREGISTERED(): string
    {
        return self::$status_UNREGISTERED;
    }

    public static function USER(): string
    {
        return self::$status_USER;
    }

    public static function ADMIN(): string
    {
        return self::$status_ADMIN;
    }

    public function add_renderer_POST(callable $renderer, string $caller_parameter_name = "", bool $needs_database = false, Validator $validator = null): Pangine
    {
        $renderer_wrapper = function () use ($renderer, $caller_parameter_name, $needs_database,$validator) {
            if ($_SERVER['REQUEST_METHOD'] == "POST" && ($caller_parameter_name == "" || isset($_POST[$caller_parameter_name]))) {
                $validator?->validate();
                self::parameter_sanitizer("POST");
                if ($needs_database) {
                    $db = new Database();
                    $renderer($db);
                    $db->close();
                } else {
                    $renderer();
                }
                Validator::clear_session_parameters();
                exit();
            }
        };
        if ($caller_parameter_name == "") {
            $this->controlled_renderers_pure[] = $renderer_wrapper;
        } else {
            $this->controlled_renderers_unpure[] = $renderer_wrapper;
        }
        return $this;
    }

    public function add_renderer_GET(callable $renderer, string $caller_parameter_name = "", bool $needs_database = false, Validator $validator = null): Pangine
    {
        $renderer_wrapper = function () use ($renderer, $caller_parameter_name, $needs_database, $validator) {
            if ($_SERVER['REQUEST_METHOD'] == "GET" && ($caller_parameter_name == "" || isset($_GET[$caller_parameter_name]))) {
                $validator?->validate();
                self::parameter_sanitizer("GET");
                if ($needs_database) {
                    $db = new Database();
                    $renderer($db);
                    $db->close();
                } else {
                    $renderer();
                }
                Validator::clear_session_parameters();
                exit();
            }
        };
        if ($caller_parameter_name == "") {
            $this->controlled_renderers_pure[] = $renderer_wrapper;
        } else {
            $this->controlled_renderers_unpure[] = $renderer_wrapper;
        }
        return $this;
    }

    private function try_session(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION["user"]["status"])) {
            $_SESSION["user"]["status"] = self::$status_UNREGISTERED;
        }
    }

    public static function navbar_list(): string
    {
        $navLinks = "";
        foreach (self::$pages as $page_title => $page_metadata) {
            $link = $page_metadata["path"];
            $pageName = $page_title;
            $allowedStatus = $page_metadata["privileges"];
            $show_in_navbar = $page_metadata["show_in_navbar"];
            if (in_array($_SESSION["user"]["status"], $allowedStatus) && $show_in_navbar) {
                if ($_SERVER['REQUEST_URI'] == (self::path(). $link) ) {
                    $navLinks .= "<li class='selectedNavLink'>" . $pageName . "</li>";
                } else {
                    $navLinks .= "<li><a href='" . $link . "'>" . $pageName . "</a></li>";
                }
            }
        }
        return $navLinks;
    }

    /**
     * @throws Exception500
     */
    public static function breadcrumbs_generator(array $breadcrumbs_array): string
    {
        if (count($breadcrumbs_array) == 0) {
            throw new Exception500("Fornire una breadcrumb con almeno una pagina.");
        }
        $breadcrumb_str = "<div class='breadcrumb-container'><p>Ti trovi in: </p><p>";
        $last_breadcrumb = end($breadcrumbs_array);
        foreach ($breadcrumbs_array as $breadcrumb) {
            if (strcmp($breadcrumb, $last_breadcrumb) == 0) {
                $breadcrumb_str .= $breadcrumb;
            } else {
                if (!isset(self::$pages[$breadcrumb])) {
                    throw new Exception500("Fornire una breadcrumb riferita ad una pagina realmente esistente.");
                }
                $breadcrumb_str .= "<a href='" . self::$pages[$breadcrumb]["path"] . "'>";
                if ($breadcrumb == "Home") {
                    $breadcrumb = "<span lang='en'>Home</span>";
                }
                $breadcrumb_str .= $breadcrumb . "</a> &gt;&gt; ";
            }
        }
        return $breadcrumb_str . "</p></div>";
    }

    private static function parameter_sanitizer(string $method): void{
        if ($method == "GET"){
            foreach ($_GET as $parameter_name => $value) {
                if(is_string($value)){
                    $_GET[$parameter_name] = trim(htmlspecialchars($value, encoding: "UTF-8"));
                }
            }
        }else{
            foreach ($_POST as $parameter_name => $value) {
                if(is_string($value)){
                    if($parameter_name == "password"){
                        $_POST[$parameter_name] = hash('sha256',$value);
                    }else{
                        $_POST[$parameter_name] = trim(htmlspecialchars($value, encoding: "UTF-8"));
                    }
                }
            }
        }
    }

    static function set_general_message(string $message, string $type = "err"):void{
        if($type == "err"){
           $_SESSION["general-message"] = "<p class='errorMessage'>".$message."</p>";
        }else{
            $_SESSION["general-message"] = "<p class='successMessage'>".$message."</p>";
        }
    }
}
