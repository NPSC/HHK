<?php
public static function calcZipDistance(\PDO $dbh, $sourceZip, $destZip) {

        if (strlen($destZip) > 5) {
            $destZip = substr($destZip, 0, 5);
        }

        if ($destZip == $sourceZip) {
            return 0;
        }

        $miles = 0;
        $stmt = $dbh->prepare("select Zip_Code, Lat, Lng from postal_codes where Zip_Code in (:src, :dest)");
        $stmt->execute(array(':src' => $sourceZip, ':dest' => $destZip));

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
        if (count($rows) == 2) {
            $miles = self::calcDist($rows[0][1], $rows[0][2], $rows[1][1], $rows[1][2]);
        } else {
            throw new RuntimeException("One or both zip codes not found in zip table, source=$sourceZip, dest=$destZip.  ");
        }
        return $miles;
    }

    protected static function calcDist($lat_A, $long_A, $lat_B, $long_B) {

        $distance = sin(deg2rad((double)$lat_A)) * sin(deg2rad((double)$lat_B)) + cos(deg2rad((double)$lat_A)) * cos(deg2rad((double)$lat_B)) * cos(deg2rad((double)$long_A - (double)$long_B));
        $distance2 = (rad2deg(acos($distance))) * 69.09;

        return $distance2;
    }

82834
87004
87410
82604
81521
80017
82601
82601
81137
81047
80524
69033
82501
80433
80525
80547
67855
80550
81428
87144
81144
80401
87102
59101
69162
80631
57717
87144
57702
87325
80525
80226
87114
87110
80205
82009
80631
87002
80538
87417
81625
57107
82009
80549
59102
81151
99516
87110
82301
NULL
81435
81507
82072-6914
82716
81039
NULL
81435
80215
82520
81007
87415
35057
81122
82801
82636
87507
87565
67701
80758
81647
82601
87144
82001
87144
87031
NULL
81507
NULL
67701
NULL
87124
87124
83301
82716
83301
81007
80537
80521
NULL
82639
82604
80817
82240
81425
87035
80446
80549
87025
81230
80537
80537
82701
NULL
82601
69341
82732
82072-6914
81201
81151
NULL
88230
81601
81004
81007
59327
NULL
81639
81526-9723
69301
82601
NULL
87110
81007
68154
NULL
81151
81507-1116
81301-8988
35057
69341
36305
NULL
72412
80751
82718
81211
35057
82718
80134
81652
87532
80649
82716
81503
80817
82501
81151
81428
87509
87509
81416
82520
82301-4710
81151
82636
80205
80205
87323-0741
67701
80301
82601
80526
80909
87402
81505
80212
81503
82072-6914
NULL
82520
69341
83301
80902
80612
81507
81601
80817
59860
80549
59102
59937
69201
81432-1333
82604
80033
87544
82240
82520
67701
82070
83704
82801
59102
81504
80550
80817
81520
82716
59501
81520
80022
82001
81201
80435
NULL
81635
NULL
87114
67205
80433
81067
81521
NULL
80930
NULL
80205
82520
82007
81211
NULL
80920
NULL
81503
NULL
80911
80909
NULL
NULL
81212
87004
87144
82240
NULL
87004
NULL
NULL
NULL
81120
80516
NULL
99516
82007
87001
88240
80219
82001
59803
81435
NULL
83646
87509
80909
85301
NULL
81507
80828
81230
NULL
NULL
NULL
87144
87120
NULL
59937
81652
87504
97140
NULL
59920
82072
82072
NULL
80911
18052
80909
15902
80457
81039
80904
57702
81001
18052
NULL
82601
59901
80433
81505
NULL
80219
33604
81101
NULL
87413
80911
87121
NULL
NULL
87144
80909
80817
80102
80903
67701
NULL
NULL
NULL
NULL
NULL
NULL
NULL
NULL
80817
NULL
87121
NULL
60612
81052
81505
NULL
80033
80863
NULL
80162
87507
80916
81052
81052
80828
80533
37148
87001
80903
81521
NULL
82716
81507
NULL
80017
67950
80017
81052
NULL
80631
82009-3034
59101
81137
NULL
80917
80863
87121
80424
82240
80537
59261
81328
87015
81432-1333
80918
80907
80303
82001
88001
88230
80525
81120
82901
80503
75501
88063
81401
82636
87144
80504
80537
80831
80504
80631
59102
80817
82001
81212
81505
87015
87144
80910
82321
67601
59471
80927
81047
80701
67701


?>