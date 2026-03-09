<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Contracts\BlockchainProviderInterface;
use Illuminate\Http\Request;
use Modules\Web3\Events\BlockchainActivityDetected;

class MoralisController extends Controller implements BlockchainProviderInterface
{
    protected $apiKey;
    protected $apiSecret;
    protected $apiBaseUrl;
    protected $gatewayBaseUrl;
    protected $environment;

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");

        $this->environment = config("metadata.system.app.zentrotraderbot.ramp.environment");

        $this->apiBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".api");
        $this->gatewayBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".gateway");
    }

    public function processWebhook(Request $request): array
    {
        // Obtenemos el parámetro 'code' definido en la ruta Route::post('/moralis/{code}', ...)
        $code = $request->route('code');

        $payload = $request->all();

        // Logueamos siempre para auditoría interna
        Log::debug("🐞 MoralisController processWebhook: Evento recibido para tenant $code: " . json_encode($payload));

        // 1. Extraemos datos limpios
        $data = $this->extractData($payload);
        $data['tenant_code'] = $code;

        // Disparamos el evento para que cualquier otro módulo lo capture
        event(new BlockchainActivityDetected($data));

        return $payload;
    }

    private function extractData(array $item): array
    {
        // Determinamos si es una transferencia nativa (ETH/MATIC/BNB) o ERC20
        $isErc20 = !empty($item['erc20Transfers']);
        $transferData = $isErc20 ? $item['erc20Transfers'][0] : ($item['txs'][0] ?? []);

        return [
            'network_id' => hexdec($item['chainId']),
            'confirmed' => $item['confirmed'],
            'block_number' => $item['block']['number'],
            'tx_hash' => $isErc20 ? $transferData['transactionHash'] : $transferData['hash'],
            'from' => $isErc20 ? $transferData['from'] : $transferData['fromAddress'],
            'to' => $isErc20 ? $transferData['to'] : $transferData['toAddress'],
            'value' => $isErc20 ? $transferData['valueWithDecimals'] : ($transferData['value'] / 1e18), // Ajuste simple
            'token_symbol' => $isErc20 ? $transferData['tokenSymbol'] : '',
            'token_address' => $isErc20 ? $transferData['contract'] : null,
            'timestamp' => $item['block']['timestamp'],
        ];
    }

    /*
    [
    {
        "confirmed": false,
        "chainId": "0x89",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "83940709",
            "hash": "0xa01b453fbb5da639b5cf2f4d8824cb2c316c922882d58fd70446611530f2c167",
            "timestamp": "1773001451"
        },
        "logs": [],
        "txs": [
            {
                "hash": "0x6c7c651f098673e15579b9f0e4c729838103c79e35576be95655e10e5ab56b53",
                "gas": "21000",
                "gasPrice": "82975790854",
                "nonce": "77",
                "input": "0x",
                "transactionIndex": "258",
                "fromAddress": "0x1aaffcab3cb8ec9b207b191c1b2e2ec662486666",
                "toAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "1000000000000000000",
                "type": "0",
                "v": "309",
                "r": "67440965791961738564605205879406119933252978751106498546872090975885759934666",
                "s": "37959983201033280944785397874690324507321117441443272692143751022335972316858",
                "receiptCumulativeGasUsed": "83803702",
                "receiptGasUsed": "21000",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.001742491607934000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },
    {
        "confirmed": true,
        "chainId": "0x89",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "83940709",
            "hash": "0xa01b453fbb5da639b5cf2f4d8824cb2c316c922882d58fd70446611530f2c167",
            "timestamp": "1773001451"
        },
        "logs": [],
        "txs": [
            {
                "hash": "0x6c7c651f098673e15579b9f0e4c729838103c79e35576be95655e10e5ab56b53",
                "gas": "21000",
                "gasPrice": "82975790854",
                "nonce": "77",
                "input": "0x",
                "transactionIndex": "258",
                "fromAddress": "0x1aaffcab3cb8ec9b207b191c1b2e2ec662486666",
                "toAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "1000000000000000000",
                "type": "0",
                "v": "309",
                "r": "67440965791961738564605205879406119933252978751106498546872090975885759934666",
                "s": "37959983201033280944785397874690324507321117441443272692143751022335972316858",
                "receiptCumulativeGasUsed": "83803702",
                "receiptGasUsed": "21000",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.001742491607934000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },
    {
        "confirmed": false,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85457435",
            "hash": "0xf9b13011cff6efe2bf0b0d04cb4718a1c193523ccb6ba8ee376a39c1537cfcf7",
            "timestamp": "1773001994"
        },
        "logs": [
            {
                "logIndex": "470",
                "transactionHash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "address": "0x55d398326f99059ff775485246999027b3197955",
                "data": "0x000000000000000000000000000000000000000000000000058d15e176280000",
                "topic0": "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "topic1": "0x000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "topic2": "0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "topic3": null,
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txs": [
            {
                "hash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "gas": "51756",
                "gasPrice": "50000000",
                "nonce": "441",
                "input": "0xa9059cbb000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1000000000000000000000000000000000000000000000000058d15e176280000",
                "transactionIndex": "37",
                "fromAddress": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "toAddress": "0x55d398326f99059ff775485246999027b3197955",
                "value": "0",
                "type": "0",
                "v": "147",
                "r": "44420363593138484462693707861823590985175124379378580511447200093278012015474",
                "s": "27629234211253424519631730478779628554476095126418231246292892695810118385477",
                "receiptCumulativeGasUsed": "10642977",
                "receiptGasUsed": "29703",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000001485150000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [
            {
                "transactionHash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "logIndex": "470",
                "contract": "0x55d398326f99059ff775485246999027b3197955",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"],
                "from": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "to": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "400000000000000000",
                "tokenName": "Tether USD",
                "tokenSymbol": "USDT",
                "tokenDecimals": "18",
                "possibleSpam": false,
                "valueWithDecimals": "0.4"
            }
        ],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },
    {
        "confirmed": true,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85457435",
            "hash": "0xf9b13011cff6efe2bf0b0d04cb4718a1c193523ccb6ba8ee376a39c1537cfcf7",
            "timestamp": "1773001994"
        },
        "logs": [
            {
                "logIndex": "470",
                "transactionHash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "address": "0x55d398326f99059ff775485246999027b3197955",
                "data": "0x000000000000000000000000000000000000000000000000058d15e176280000",
                "topic0": "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "topic1": "0x000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "topic2": "0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "topic3": null,
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txs": [
            {
                "hash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "gas": "51756",
                "gasPrice": "50000000",
                "nonce": "441",
                "input": "0xa9059cbb000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1000000000000000000000000000000000000000000000000058d15e176280000",
                "transactionIndex": "37",
                "fromAddress": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "toAddress": "0x55d398326f99059ff775485246999027b3197955",
                "value": "0",
                "type": "0",
                "v": "147",
                "r": "44420363593138484462693707861823590985175124379378580511447200093278012015474",
                "s": "27629234211253424519631730478779628554476095126418231246292892695810118385477",
                "receiptCumulativeGasUsed": "10642977",
                "receiptGasUsed": "29703",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000001485150000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [
            {
                "transactionHash": "0x5ea9000605c6a5e6466ba0a7620a7ed57e6e54f8e871dce7b3463a8efa6abcc6",
                "logIndex": "470",
                "contract": "0x55d398326f99059ff775485246999027b3197955",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"],
                "from": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "to": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "400000000000000000",
                "tokenName": "Tether USD",
                "tokenSymbol": "USDT",
                "tokenDecimals": "18",
                "possibleSpam": false,
                "valueWithDecimals": "0.4"
            }
        ],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },

    {
        "confirmed": false,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85457962",
            "hash": "0x869547bd92827eec7a6db8dace2a5e99795f8fd4b2d5dd7a78a05da8540b5c05",
            "timestamp": "1773002231"
        },
        "logs": [],
        "txs": [
            {
                "hash": "0xbda6aaf78eb41bc43f211851b7824da0bebbdaf6e5d13203de30e9e64986e606",
                "gas": "21000",
                "gasPrice": "50000000",
                "nonce": "442",
                "input": "0x",
                "transactionIndex": "40",
                "fromAddress": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "toAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "1617250000000000",
                "type": "0",
                "v": "148",
                "r": "84904712887488465440746303852816232450917082749832729725318205589798801310720",
                "s": "15754477483119629794705887350092386900749018274481157572780760310289492838740",
                "receiptCumulativeGasUsed": "4978849",
                "receiptGasUsed": "21000",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000001050000000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },
    {
        "confirmed": true,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85457962",
            "hash": "0x869547bd92827eec7a6db8dace2a5e99795f8fd4b2d5dd7a78a05da8540b5c05",
            "timestamp": "1773002231"
        },
        "logs": [],
        "txs": [
            {
                "hash": "0xbda6aaf78eb41bc43f211851b7824da0bebbdaf6e5d13203de30e9e64986e606",
                "gas": "21000",
                "gasPrice": "50000000",
                "nonce": "442",
                "input": "0x",
                "transactionIndex": "40",
                "fromAddress": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "toAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "1617250000000000",
                "type": "0",
                "v": "148",
                "r": "84904712887488465440746303852816232450917082749832729725318205589798801310720",
                "s": "15754477483119629794705887350092386900749018274481157572780760310289492838740",
                "receiptCumulativeGasUsed": "4978849",
                "receiptGasUsed": "21000",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000001050000000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },

    {
        "confirmed": false,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85458460",
            "hash": "0xe427d7b94493240620d99e7474a2705f84a540897e437c7597579a3f4559b241",
            "timestamp": "1773002455"
        },
        "logs": [
            {
                "logIndex": "82",
                "transactionHash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "address": "0x55d398326f99059ff775485246999027b3197955",
                "data": "0x000000000000000000000000000000000000000000000000058d15e27d647a81",
                "topic0": "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "topic1": "0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "topic2": "0x000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "topic3": null,
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txs": [
            {
                "hash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "gas": "77697",
                "gasPrice": "50000000",
                "nonce": "4",
                "input": "0xa9059cbb000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7000000000000000000000000000000000000000000000000058d15e27d647a81",
                "transactionIndex": "16",
                "fromAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "toAddress": "0x55d398326f99059ff775485246999027b3197955",
                "value": "0",
                "type": "0",
                "v": "148",
                "r": "33529827006583729264829151073103375742754654875712803673877784492519992543219",
                "s": "40931781960836747216306550021700641703921352711508669271692369911057100893412",
                "receiptCumulativeGasUsed": "2878667",
                "receiptGasUsed": "46827",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000002341350000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [
            {
                "transactionHash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "logIndex": "82",
                "contract": "0x55d398326f99059ff775485246999027b3197955",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"],
                "from": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "to": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "value": "400000004416371329",
                "tokenName": "Tether USD",
                "tokenSymbol": "USDT",
                "tokenDecimals": "18",
                "possibleSpam": false,
                "valueWithDecimals": "0.400000004416371329"
            }
        ],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    },
    {
        "confirmed": true,
        "chainId": "0x38",
        "abi": [
            {
                "name": "Transfer",
                "type": "event",
                "anonymous": false,
                "inputs": [
                    { "type": "address", "name": "from", "indexed": true },
                    { "type": "address", "name": "to", "indexed": true },
                    { "type": "uint256", "name": "value", "indexed": false }
                ]
            }
        ],
        "streamId": "d84f7739-9723-4616-a000-82c0d82d5920",
        "tag": null,
        "retries": 0,
        "block": {
            "number": "85458460",
            "hash": "0xe427d7b94493240620d99e7474a2705f84a540897e437c7597579a3f4559b241",
            "timestamp": "1773002455"
        },
        "logs": [
            {
                "logIndex": "82",
                "transactionHash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "address": "0x55d398326f99059ff775485246999027b3197955",
                "data": "0x000000000000000000000000000000000000000000000000058d15e27d647a81",
                "topic0": "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                "topic1": "0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "topic2": "0x000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "topic3": null,
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txs": [
            {
                "hash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "gas": "77697",
                "gasPrice": "50000000",
                "nonce": "4",
                "input": "0xa9059cbb000000000000000000000000765a7b0c1d18d33bdf3db528db118a2316ea0ec7000000000000000000000000000000000000000000000000058d15e27d647a81",
                "transactionIndex": "16",
                "fromAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "toAddress": "0x55d398326f99059ff775485246999027b3197955",
                "value": "0",
                "type": "0",
                "v": "148",
                "r": "33529827006583729264829151073103375742754654875712803673877784492519992543219",
                "s": "40931781960836747216306550021700641703921352711508669271692369911057100893412",
                "receiptCumulativeGasUsed": "2878667",
                "receiptGasUsed": "46827",
                "receiptContractAddress": null,
                "receiptRoot": null,
                "receiptStatus": "1",
                "transactionFee": "0.000002341350000000",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"]
            }
        ],
        "txsInternal": [],
        "erc20Transfers": [
            {
                "transactionHash": "0xb892d4c01210b2707b47147fa479f45e78d60248994ffedbb386faf149a2068e",
                "logIndex": "82",
                "contract": "0x55d398326f99059ff775485246999027b3197955",
                "triggered_by": ["0xd2531438b90232f4aab4ddfc6f146474e84e1ea1"],
                "from": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "to": "0x765a7b0c1d18d33bdf3db528db118a2316ea0ec7",
                "value": "400000004416371329",
                "tokenName": "Tether USD",
                "tokenSymbol": "USDT",
                "tokenDecimals": "18",
                "possibleSpam": false,
                "valueWithDecimals": "0.400000004416371329"
            }
        ],
        "erc20Approvals": [],
        "nftTokenApprovals": [],
        "nftApprovals": { "ERC721": [], "ERC1155": [] },
        "nftTransfers": [],
        "nativeBalances": []
    }
]

    */
}