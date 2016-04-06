<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/scavengerhunt/locallib.php");

class mod_scavengerhunt_external_fetch_scavengerhunt extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function fetch_scavengerhunt_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function fetch_scavengerhunt_parameters() {
        return new external_function_parameters(
                array(
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
                )
        );
    }

    public static function fetch_scavengerhunt_returns() {
        return new external_single_structure(
                array(
            'scavengerhunt' => new external_single_structure(
                    array(
                'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the scavengerhunt'),
                'roads' => new external_value(PARAM_RAW, 'json with all roads of the stage'))),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function fetch_scavengerhunt($idScavengerhunt) { //Don't forget to set it as static
        self::validate_parameters(self::fetch_scavengerhunt_parameters(), array('idScavengerhunt' => $idScavengerhunt));

        $scavengerhunt = array();
        $status = array();

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:getscavengerhunt', $context);
        list($scavengerhunt['riddles'], $scavengerhunt['roads']) = getScavengerhunt($idScavengerhunt, $context);
        $status['code'] = 0;
        $status['msg'] = 'La caza del tesoro se ha cargado con éxito';

        $result = array();
        $result['scavengerhunt'] = $scavengerhunt;
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_update_riddles extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function update_riddles_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_riddles_parameters() {
        return new external_function_parameters(
                array(
            'riddles' => new external_value(PARAM_RAW, 'GeoJSON with all riddles to update'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function update_riddles_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function update_riddles($riddles, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        global $DB, $USER;
        self::validate_parameters(self::update_riddles_parameters(), array('riddles' => $riddles, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editriddle', $context);
        $features = geojson_to_object($riddles);
        if (checkLock($idScavengerhunt, $idLock, $USER->id)) {
            try {
                $transaction = $DB->start_delegated_transaction();
                foreach ($features as $feature) {
                    updateRiddleBD($feature);
                }
                $transaction->allow_commit();
                $status['code'] = 0;
                $status['msg'] = 'La actualización de las pistas se ha realizado con éxito';
            } catch (Exception $e) {
                $transaction->rollback($e);
                $status['code'] = 1;
                $status['msg'] = $e;
            }
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_delete_riddle extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_riddle_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_riddle_parameters() {
        return new external_function_parameters(
                array(
            'idRiddle' => new external_value(PARAM_RAW, 'id of riddle'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_riddle_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_riddle($idRiddle, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::delete_riddle_parameters(), array('idRiddle' => $idRiddle, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));
//Recojo todas las features

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editriddle', $context);
        if (checkLock($idScavengerhunt, $idLock, $USER->id)) {
            deleteEntryBD($idRiddle);
            $status['code'] = 0;
            $status['msg'] = 'La eliminación de la pista se ha realizado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_delete_road extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function delete_road_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_road_parameters() {
        return new external_function_parameters(
                array(
            'idRoad' => new external_value(PARAM_INT, 'id of road'),
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock')
                )
        );
    }

    public static function delete_road_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
        ));
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function delete_road($idRoad, $idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::delete_road_parameters(), array('idRoad' => $idRoad, 'idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        require_capability('mod/scavengerhunt:editroad', $context);
        if (checkLock($idScavengerhunt, $idLock, $USER->id)) {
            deleteRoadBD($idRoad);
            $status['code'] = 0;
            $status['msg'] = 'El camino se ha eliminado con éxito';
        } else {
            $status['code'] = 1;
            $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
        }

        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_renew_lock extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function renew_lock_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function renew_lock_parameters() {
        return new external_function_parameters(
                array(
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'idLock' => new external_value(PARAM_INT, 'id of lock', VALUE_OPTIONAL)
                )
        );
    }

    public static function renew_lock_returns() {
        return new external_single_structure(
                array(
            'idLock' => new external_value(PARAM_INT, 'id of lock'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function renew_lock($idScavengerhunt, $idLock) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::renew_lock_parameters(), array('idScavengerhunt' => $idScavengerhunt, 'idLock' => $idLock));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:managescavenger', $context);
        if (isset($idLock)) {
            if (checkLock($idScavengerhunt, $idLock, $USER->id)) {
                $idLock = renewLockScavengerhunt($idScavengerhunt,$USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha renovado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'Se ha editado esta caza del tesoro, recargue esta página';
            }
        } else {
            if (!isLockScavengerhunt($idScavengerhunt,$USER->id)) {
                $idLock = renewLockScavengerhunt($idScavengerhunt,$USER->id);
                $status['code'] = 0;
                $status['msg'] = 'Se ha creado el bloqueo con exito';
            } else {
                $status['code'] = 1;
                $status['msg'] = 'La caza del tesoro está siendo editada';
            }
        }
        $result = array();
        $result['status'] = $status;
        $result['idLock'] = $idLock;
        return $result;
    }

}

class mod_scavengerhunt_external_validate_location extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function validate_location_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function validate_location_parameters() {
        return new external_function_parameters(
                array(
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
            'location' => new external_value(PARAM_RAW, "GeoJSON with point's location")
                )
        );
    }

    public static function validate_location_returns() {
        return new external_single_structure(
                array(
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function validate_location($idScavengerhunt, $location) { //Don't forget to set it as static
        GLOBAL $USER;
        self::validate_parameters(self::validate_location_parameters(), array('idScavengerhunt' => $idScavengerhunt, 'location' => $location));

        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:view', $context);
        $params = getUserGroupAndRoad($USER->id,$idScavengerhunt, $cm, $cm->modinfo->course->id);
        if (checkRiddle($USER->id,$params->group_id, $params->idroad, geojson_to_object($location), $params->groupmode)) {
            $status['code'] = 0;
            $status['msg'] = '¡¡¡Enhorabuena, a por la siguiente pista!!!';
        } else {
            $status['code'] = 0;
            $status['msg'] = 'No es el lugar correcto';
        }
        $result = array();
        $result['status'] = $status;
        return $result;
    }

}

class mod_scavengerhunt_external_user_progress extends external_api {

    /**
     * Can this function be called directly from ajax?
     *
     * @return boolean
     * @since Moodle 2.9
     */
    public static function user_progress_is_allowed_from_ajax() {
        return true;
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function user_progress_parameters() {
        return new external_function_parameters(
                array(
            'idScavengerhunt' => new external_value(PARAM_INT, 'id of scavengerhunt'),
                )
        );
    }

    public static function user_progress_returns() {
        return new external_single_structure(
                array(
            'riddles' => new external_value(PARAM_RAW, 'geojson with all riddles of the user/group'),
            'status' => new external_single_structure(
                    array(
                'code' => new external_value(PARAM_INT, 'code of status: 0(OK),1(ERROR)'),
                'msg' => new external_value(PARAM_RAW, 'message explain code')))
                )
        );
    }

    /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function user_progress($idScavengerhunt) { //Don't forget to set it as static
        global $USER,$COURSE;
        self::validate_parameters(self::user_progress_parameters(), array('idScavengerhunt' => $idScavengerhunt));
        $cm = get_coursemodule_from_instance('scavengerhunt', $idScavengerhunt);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/scavengerhunt:view', $context);
        $params = getUserGroupAndRoad($USER->id,$idScavengerhunt, $cm, $COURSE->id);
        $user_riddles = getUserProgress($params->idroad, $params->groupmode, $params->group_id, $idScavengerhunt, $context);
        $status['code'] = 0;
        $status['msg'] = 'El progreso de usuario se ha cargado con éxito';
        $result = array();
        $result['status'] = $status;
        $result['riddles'] = $user_riddles;
        return $result;
    }

}
