<?php 

/* Récupération des paramètres de connexion */
include '../config.php';

/* Récupération des paramètres de la requête */
$query_ans = $_GET['query_ans'];
$query_entite = $_GET['query_entite'];
$query_entite_nom = $_GET['query_entite_nom'];
$query_sect = $_GET['query_sect'];
$query_ener = $_GET['query_ener'];
$query_var = $_GET['query_var'];
$query_detail_comm = $_GET['query_detail_comm'];

/*  Ecriture du code SQL de la requête */

// Group by 
$group_by = " GROUP BY an, lib_unite, nom_abrege_polluant";
if ($query_detail_comm == "true") {
    $group_by =  $group_by . ", \"Entité administrative\"";
    $nom_entite = " nom_comm";
} else {
    // $query_entite = str_replace("tropole", "!!!", $query_entite);   
    // echo "--------";
    // echo $query_entite_nom;
    // $query_entite_nom = str_replace("\\'", "'", $query_entite_nom);
    // echo $query_entite_nom;
    // echo "--------";
    $query_entite_nom = str_replace("\\'", "''", $query_entite_nom);
    $nom_entite = " '" . $query_entite_nom . "'";
};
if ($query_sect != "") {
    $group_by =  $group_by . ", nom_secten1";
    $nom_secten1 = "nom_secten1";
} else {
    // $nom_secten1 = "'Tous secteurs' as nom_secten1";
    $nom_secten1 = "'Tous secteurs'";
};
if ($query_ener != "") {
    $group_by =  $group_by . ", cat_energie";
    $cat_energie = "cat_energie";
} else {
    $cat_energie = "'Toutes énergies'";
};
// echo $group_by;

// Where
$where = "WHERE ";
$where =  $where . " an in (" . $query_ans . ")";
$where =  $where . " and id_polluant in (" . $query_var . ")";
if ($query_sect != "") {
    $where =  $where . " and id_secten1 in (" . str_replace("\\", "", $query_sect) . ")";
};
if ($query_ener != "") {
    $where =  $where . " and code_cat_energie in (" . $query_ener . ")";
};
if ($query_entite == "93") {
    $where =  $where;
} elseif (
    $query_entite == "4" || 
    $query_entite == "5" || 
    $query_entite == "6" || 
    $query_entite == "13" || 
    $query_entite == "83" || 
    $query_entite == "84" 
) {
    $where =  $where . " and id_comm / 1000 in (" . $query_entite . ")";
} elseif (strlen ($query_entite) == 9) {
    $where =  $where . " and id_comm in (select distinct id_comm from commun.tpk_commune_2015_2016 where siren_epci_2017 = " . $query_entite . ")";
} else {
    $where =  $where . " and id_comm in (" . $query_entite . ")";
};
// echo $where;

// SS
if ($query_detail_comm == "false" and $query_entite == "93") { // --  and $query_sect == "") {
    $ss = "FALSE";
} else {
    $ss = "TRUE";
};

// Choix de la colonne SS à interroger
if (strlen ($query_entite) == 9 and $query_detail_comm == "false") {
    $ss_field = "ss_epci";
} else {
    $ss_field = "ss";
};

$sql = "
select 
    an as \"Année\", 
    " . $nom_entite . "  as \"Entité administrative\",  
    " . $nom_secten1 . " as \"Activité\",  
    " . $cat_energie . " as \"Energie\", 
    nom_abrege_polluant as \"Variable\", 
    round(sum(val)::numeric, 1) as \"Valeur\", 
    coalesce(lib_unite, 'Secret Stat') as \"Unite\"
from (
	select an, id_comm, id_secten1, code_cat_energie, id_polluant, 
    sum(case when " . $ss . " is TRUE and " . $ss_field . " is TRUE then null else val end) as val, 
    case when " . $ss . " is TRUE and " . $ss_field . " is TRUE then NULL else id_unite end as id_unite
	from total.bilan_comm_v4_secten1
	" . $where . "     
	and (id_secten1, id_polluant) not in (('1', 131),('1', 15),('1', 128))    
    group by 
        an, 
        case when " . $ss . " is TRUE and " . $ss_field . " is TRUE then NULL else id_unite end, 
        id_polluant, id_comm, id_secten1, code_cat_energie
)  as a
left join commun.tpk_communes as b using (id_comm)
left join transversal.tpk_secten1 as c using (id_secten1)
left join (select distinct code_cat_energie, cat_energie from transversal.tpk_energie) as d using (code_cat_energie)
left join commun.tpk_polluants as e using (id_polluant)
left join commun.tpk_unite as f using (id_unite)
" . $group_by . "
-- order by an, nom_entite, nom_secten1, cat_energie, nom_abrege_polluant, lib_unite
order by \"Année\", \"Entité administrative\", \"Activité\", \"Energie\", \"Variable\", \"Unite\"
;
";

// Si extraction des consos d'énergie du secteur prod énergie résultat null ce qui est normal.
// On renvoie un texte d'avertissement
if (str_replace("\\", "", $query_sect) == "'1'" and $query_var == "131") { 
    // echo "Warning msg";
    $sql = "SELECT '<font color=\"#ff6600\">Les consommations du secteur ''Extraction, transformation et distribution d''énergie'' sont considérées comme de l''énergie primaire et ne figurent pas dans le bilan des consommations finales. <a href=\"methodo.php\">[Plus d''informations]</a></font>' as Warning";
};

// echo $ss;
// echo $ss_field;
// echo $sql;

/* Connexion à PostgreSQL */
$conn = pg_connect("dbname='" . $pg_bdd . "' user='" . $pg_lgn . "' password='" . $pg_pwd . "' host='" . $pg_host . "'");
if (!$conn) {
    echo "Not connected";
    exit;
}

/* Execution de la requête */
$rResult = pg_query($conn, $sql);
if (!$rResult) {
    echo "An SQL error occured.\n";
    exit;
}

/* Récupération des résultats */
$output = array();
while ($row = pg_fetch_assoc( $rResult )) {
  $output[] = $row;
} 
     
/* Export en JSON */
header('Content-Type: application/json');
echo json_encode( $output );
?>
