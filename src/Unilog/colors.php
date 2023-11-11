<?php
define('FORE_BLACK', "\e[0;30");
define('FORE_DGREY', "\e[1;30");
define('FORE_RED',   "\e[0;31");
define('FORE_LRED',  "\e[1;31");
define('FORE_GREEN', "\e[0;32");
define('FORE_LGREEN',"\e[1;32");
define('FORE_BROWN', "\e[0;33");
define('FORE_YELLOW',"\e[1;33");
define('FORE_BLUE',  "\e[0;34");
define('FORE_LBLUE', "\e[1;34");
define('FORE_MAGEN', "\e[0;35");
define('FORE_LMAGEN',"\e[1;35");
define('FORE_CYAN',  "\e[0;36");
define('FORE_LCYAN', "\e[1;36");
define('FORE_LGREY', "\e[0;37");
define('FORE_WHITE', "\e[1;37");

define('BACK_BLACK', ';40m');
define('BACK_RED',   ';41m');
define('BACK_GREEN', ';42m');
define('BACK_YELLOW',';43m');
define('BACK_BLUE',  ';44m');
define('BACK_MAGEN', ';45m');
define('BACK_CYAN',  ';46m');
define('BACK_LGREY', ';47m');
define('BACK_DEF', BACK_BLACK);
define('FORE_DEF', FORE_LGREY);

define('COLOR_REGULAR', FORE_DEF.BACK_BLACK);
define('COLOR_DARK', FORE_DGREY.BACK_BLACK);
define('COLOR_BRIGHT', FORE_CYAN.BACK_BLACK);
define('COLOR_BRIGHT_GREEN', FORE_LGREEN.BACK_BLACK);
define('COLOR_GOOD', FORE_LGREEN.BACK_BLACK);
define('COLOR_EVENT', FORE_YELLOW.BACK_BLACK);
define('COLOR_DANGER', FORE_BLACK.BACK_RED);
define('COLOR_WARNING', FORE_RED.BACK_BLACK);
define('COLOR_INFO', FORE_LCYAN.BACK_BLACK);

define('COLOR_YELLOW', FORE_YELLOW.BACK_BLACK);
define('COLOR_RED', FORE_RED.BACK_BLACK);
define('COLOR_WHITE', FORE_WHITE.BACK_BLACK);
define('COLOR_GREY', FORE_LGREY.BACK_BLACK);
define('COLOR_DGREY', FORE_DGREY.BACK_BLACK);
define('COLOR_GREEN', FORE_GREEN.BACK_BLACK);
