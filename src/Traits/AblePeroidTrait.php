<?php
namespace Swango\Model\Traits;
trait AblePeriodTrait {
    public function isInAbleTime(): bool {
        [
            $week,
            $hour,
            $min
        ] = explode(' ', date('w G i', \Time\now()));
        if (($this->able_period_week & (2 ** (int)$week)) === 0)
            return false;
        $p = (int)$hour * 2;
        if ((int)$min > 29)
            ++ $p;
        $pow = 2 ** $p;
        $now = ($this->able_period_day & $pow) > 0;
        return $now;
    }
    public function getAbleperiodWeekInString(?int $able_period_week = null, string $glue = ' '): string {
        if (! isset($able_period_week))
            $able_period_week = $this->able_period_week;
        if ($able_period_week == 127)
            return '';
        if ($able_period_week == 0)
            return '全周关闭';
        $week = new \SplFixedArray(9);
        $week[0] = '日';
        $week[1] = '一';
        $week[2] = '二';
        $week[3] = '三';
        $week[4] = '四';
        $week[5] = '五';
        $week[6] = '六';
        $week[7] = null;
        $week[8] = null;
        for($i = 0, $pow = 1; $i < 7; ++ $i, $pow *= 2)
            if (($able_period_week & $pow) === 0)
                $week[$i] = null;
        // if ($week[0] !== null) {
        // $week[0] = null;
        // $week[7] = '日';
        // }
        $ret = [];
        for($i = 0; $i <= 6; ++ $i)
            if ($week[$i] !== null) {
                for($j = $i, ++ $i; $i <= 7 && $week[$i] !== null; ++ $i) {}
                if ($i - 1 == $j)
                    $ret[] = '周' . $week[$j];
                else
                    $ret[] = '周' . $week[$j] . '~' . $week[$i - 1];
            }
        return implode($glue, $ret);
    }
    private static function getTimeStringByPoint(int $p): string {
        $hour = (int)($p / 2);
        if ($hour < 10)
            $hour = '0' . $hour;
        $min = $p % 2 == 0 ? '00' : '30';
        return "$hour:$min";
    }
    public function getAbleperiodDayInString(?int $able_period_day = null, string $glue = '<BR>'): string {
        if (! isset($able_period_day))
            $able_period_day = $this->able_period_day;
        if ($able_period_day == 281474976710655)
            return '24小时可用';
        if ($able_period_day == 0)
            return '全天不可用';
        $ret = [];
        $p = 0;
        $pow = 1;
        do {
            for(; $p < 48; ++ $p, $pow *= 2)
                if (($able_period_day & $pow) != 0) {
                    $last = $p;
                    break;
                }
            ++ $p;
            $pow *= 2;
            for(; $p < 48; ++ $p, $pow *= 2)
                if (($able_period_day & $pow) == 0) {
                    $ret[] = [
                        self::getTimeStringByPoint($last),
                        self::getTimeStringByPoint($p)
                    ];
                    break;
                }
            if ($p == 48) {
                $ret[] = [
                    self::getTimeStringByPoint($last),
                    '24:00'
                ];
                break;
            }
            ++ $p;
            $pow *= 2;
        } while ( $p < 48 );
        foreach ($ret as &$set)
            $set = $set[0] . '~' . $set[1];
        return implode($glue, $ret);
    }
}