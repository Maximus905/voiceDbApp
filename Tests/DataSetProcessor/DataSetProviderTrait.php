<?php

trait DataSetProviderTrait
{
    public function providerInvalidApplianceDataSetError()
    {
        return [
            ['{"applianceModules":[{"key 2": "val 2"}]}'],
        ];
    }

    public function providerInvalidClusterDataSetError_1()
    {
        return [
            ['{"clusterAppliances":[{"key 2": "val 2"}]}'],
        ];
    }

    public function providerInvalidClusterDataSetError_2()
    {
        return [
            ['{"hostname": "val 1","clusterAppliances":[]}'],
        ];
    }

    public function providerInvalidClusterDataSetError_3()
    {
        return [
            ['{"hostname": "val 1","clusterAppliances":[{}]}'],
        ];
    }

    public function providerValidApplianceDataSet()
    {
        return [
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 2",
                            "product_number":"pr_num 2",
                            "description":"desc 2"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        },
                        {
                            "serial":"sn 5",
                            "product_number":"pr_num 5",
                            "description":"desc 5"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }'
            ]
        ];
    }

    public function providerValidClusterDataSet()
    {
        return [
            [
                '{
                "platformSerial": "u",
                "LotusId": "2",
                "hostname": "c",
                "applianceType": "c",
                "softwareVersion": "11",
                "chassis": "C",
                "platformTitle": "C",
                "ip": "1.1.2.1/32",
                "applianceSoft": "C",
                "platformVendor": "C",
                "clusterAppliances":
                    [
                        {
                            "platformSerial": "F",
                            "applianceModules":
                                [
                                    {
                                        "serial": "A",
                                        "product_number": "1",
                                        "description": "1"
                                    }
                                ],
                            "LotusId": "2",
                            "hostname": "c",
                            "applianceType": "a",
                            "softwareVersion": "1",
                            "chassis": "W",
                            "platformTitle": "W",
                            "ip": "1.1.2.1/32",
                            "applianceSoft": "C",
                            "platformVendor": "C"
                        },
                        {
                            "platformSerial": "CQ",
                            "applianceModules": [],
                            "LotusId": "2",
                            "hostname": "c",
                            "applianceType": "a",
                            "softwareVersion": "11",
                            "chassis": "W",
                            "platformTitle": "W",
                            "ip": "1.1.2.1/32",
                            "applianceSoft": "C3",
                            "platformVendor": "C"
                        }
                    ]
                }'
            ]
        ];
    }

    public function providerValidDataSet()
    {
        return [
            [
                '{
                "platformSerial": "u",
                "LotusId": "2",
                "hostname": "c",
                "applianceType": "c",
                "softwareVersion": "11",
                "chassis": "C",
                "platformTitle": "C",
                "ip": "1.1.2.1/32",
                "applianceSoft": "C",
                "platformVendor": "C",
                "clusterAppliances":
                    [
                        {
                            "platformSerial": "F",
                            "applianceModules":
                                [
                                    {
                                        "serial": "A",
                                        "product_number": "1",
                                        "description": "1"
                                    }
                                ],
                            "LotusId": "2",
                            "hostname": "c",
                            "applianceType": "a",
                            "softwareVersion": "1",
                            "chassis": "W",
                            "platformTitle": "W",
                            "ip": "1.1.2.1/32",
                            "applianceSoft": "C",
                            "platformVendor": "C"
                        },
                        {
                            "platformSerial": "CQ",
                            "applianceModules": [],
                            "LotusId": "2",
                            "hostname": "c",
                            "applianceType": "a",
                            "softwareVersion": "11",
                            "chassis": "W",
                            "platformTitle": "W",
                            "ip": "1.1.2.1/32",
                            "applianceSoft": "C3",
                            "platformVendor": "C"
                        }
                    ]
                }'
            ],
            [
                '{
                    "platformSerial":"testPS",
                    "applianceModules":
                    [
                        {
                            "serial":"sn 1",
                            "product_number":"pr_num 1",
                            "description":"desc 1"
                        },
                        {
                            "serial":"sn 2",
                            "product_number":"pr_num 2",
                            "description":"desc 2"
                        },
                        {
                            "serial":"sn 3",
                            "product_number":"pr_num 3",
                            "description":"desc 3"
                        },
                        {
                            "serial":"sn 4",
                            "product_number":"pr_num 4",
                            "description":"desc 4"
                        },
                        {
                            "serial":"sn 5",
                            "product_number":"pr_num 5",
                            "description":"desc 5"
                        }
                    ],
                    "LotusId":"1",
                    "hostname":"host",
                    "applianceType":"device",
                    "softwareVersion":"ver soft",
                    "chassis":"ch 1",
                    "platformTitle":"pl_title 1",
                    "ip":"10.100.240.195/24",
                    "applianceSoft":"soft",
                    "platformVendor":"CISCO"
                }'
            ]
        ];
    }

    public function providerDetermineDeviceType_Appliance()
    {
        return [
            ['{"hostname": "val 1"}'],
        ];
    }

    public function providerDetermineDeviceType_Cluster()
    {
        return [
            ['{"hostname": "val 1","clusterAppliances":[{}]}'],
        ];
    }
}

