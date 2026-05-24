<?php

function legacy_redirect($target)
{
    $query = $_SERVER["QUERY_STRING"] ?? "";
    $location = $target . ($query !== "" ? "?" . $query : "");
    header("Location: " . $location, true, 302);
    exit();
}
