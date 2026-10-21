# Gera NF-e de teste (XML 4.00 com protocolo) e o DANFE em PDF, para testar a leitura da nota.
import sys, os
from brazilfiscalreport.danfe import Danfe

def dv(chave43):
    pesos = [2,3,4,5,6,7,8,9]
    s = sum(int(d) * pesos[i % 8] for i, d in enumerate(reversed(chave43)))
    r = s % 11
    return '0' if r < 2 else str(11 - r)

def nota(nome, nnf, cliente, itens, xped_item=None, xped_compra=None, infcpl='', dh='2026-10-15T08:12:40-04:00', recbto='2026-10-15T08:12:55-04:00', serie='1'):
    chave43 = '51' + '2610' + '12345678000190' + '55' + serie.zfill(3) + str(nnf).zfill(9) + '1' + '12345678'
    chave = chave43 + dv(chave43)
    dets = ''
    total = 0
    for i, (cod, desc, un, q, vu) in enumerate(itens, 1):
        vt = round(q * vu, 2); total += vt
        xped = f'<xPed>{xped_item}</xPed><nItemPed>1</nItemPed>' if xped_item else ''
        dets += f'''<det nItem="{i}"><prod><cProd>{cod}</cProd><cEAN>SEM GTIN</cEAN><xProd>{desc}</xProd><NCM>25171000</NCM><CFOP>5102</CFOP><uCom>{un}</uCom><qCom>{q:.4f}</qCom><vUnCom>{vu:.10f}</vUnCom><vProd>{vt:.2f}</vProd><cEANTrib>SEM GTIN</cEANTrib><uTrib>{un}</uTrib><qTrib>{q:.4f}</qTrib><vUnTrib>{vu:.10f}</vUnTrib><indTot>1</indTot>{xped}</prod><imposto><ICMS><ICMSSN102><orig>0</orig><CSOSN>102</CSOSN></ICMSSN102></ICMS><PIS><PISOutr><CST>99</CST><vBC>0.00</vBC><pPIS>0.00</pPIS><vPIS>0.00</vPIS></PISOutr></PIS><COFINS><COFINSOutr><CST>99</CST><vBC>0.00</vBC><pCOFINS>0.00</pCOFINS><vCOFINS>0.00</vCOFINS></COFINSOutr></COFINS></imposto></det>'''
    compra = f'<compra><xPed>{xped_compra}</xPed></compra>' if xped_compra else ''
    xml = f'''<?xml version="1.0" encoding="UTF-8"?>
<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe{chave}" versao="4.00"><ide><cUF>51</cUF><cNF>12345678</cNF><natOp>VENDA DE MERCADORIA</natOp><mod>55</mod><serie>{serie}</serie><nNF>{nnf}</nNF><dhEmi>{dh}</dhEmi><dhSaiEnt>{dh}</dhSaiEnt><tpNF>1</tpNF><idDest>1</idDest><cMunFG>5103403</cMunFG><tpImp>1</tpImp><tpEmis>1</tpEmis><cDV>{chave[-1]}</cDV><tpAmb>1</tpAmb><finNFe>1</finNFe><indFinal>0</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>ERP 1.0</verProc></ide><emit><CNPJ>12345678000190</CNPJ><xNome>PROSPERA MINERACAO LTDA</xNome><xFant>PROSPERA</xFant><enderEmit><xLgr>RODOVIA MT 251</xLgr><nro>KM 10</nro><xBairro>ZONA RURAL</xBairro><cMun>5103403</cMun><xMun>CUIABA</xMun><UF>MT</UF><CEP>78000000</CEP><cPais>1058</cPais><xPais>BRASIL</xPais><fone>6533334444</fone></enderEmit><IE>131234567</IE><CRT>1</CRT></emit><dest><CNPJ>98765432000110</CNPJ><xNome>{cliente}</xNome><enderDest><xLgr>AV DAS TORRES</xLgr><nro>1500</nro><xBairro>CENTRO</xBairro><cMun>5103403</cMun><xMun>CUIABA</xMun><UF>MT</UF><CEP>78050000</CEP><cPais>1058</cPais><xPais>BRASIL</xPais></enderDest><indIEDest>1</indIEDest><IE>139876543</IE></dest>{dets}<total><ICMSTot><vBC>0.00</vBC><vICMS>0.00</vICMS><vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet><vProd>{total:.2f}</vProd><vFrete>0.00</vFrete><vSeg>0.00</vSeg><vDesc>0.00</vDesc><vII>0.00</vII><vIPI>0.00</vIPI><vIPIDevol>0.00</vIPIDevol><vPIS>0.00</vPIS><vCOFINS>0.00</vCOFINS><vOutro>0.00</vOutro><vNF>{total:.2f}</vNF></ICMSTot></total><transp><modFrete>1</modFrete></transp><pag><detPag><tPag>15</tPag><vPag>{total:.2f}</vPag></detPag></pag><infAdic><infCpl>{infcpl}</infCpl></infAdic>{compra}</infNFe></NFe><protNFe versao="4.00"><infProt><tpAmb>1</tpAmb><verAplic>MT_4.00</verAplic><chNFe>{chave}</chNFe><dhRecbto>{recbto}</dhRecbto><nProt>151260000123456</nProt><digVal>abc=</digVal><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></infProt></protNFe></nfeProc>'''
    caminho = os.path.join(os.path.dirname(__file__), nome)
    open(caminho + '.xml', 'w', encoding='utf-8').write(xml)
    Danfe(xml=xml).output(caminho + '.pdf')
    return chave

print(nota('nota1', 12345, 'CONSTRUTORA BOA OBRA LTDA', [('001', 'BRITA 1', 'T', 32.5, 85.0)], xped_item='PV-4521', infcpl='PEDIDO: 4521 - CARREGAMENTO NA PEDREIRA'))
print(nota('nota2', 12346, 'MUNICIPIO DE VARZEA GRANDE', [('014', 'PO DE PEDRA', 'M3', 12.0, 60.0)], infcpl='Pedido n. 7788 Placa QBK2F41 Motorista Joao'))
print(nota('nota3', 98, 'AREIAO SAO JOSE EIRELI', [('002', 'BRITA 0 (PEDRISCO)', 'TON', 10.0, 90.0), ('002', 'BRITA 0 (PEDRISCO)', 'TON', 5.5, 90.0)], xped_compra='OC-2026/115', infcpl='Entrega na obra da Av. Beira Rio', serie='2', dh='2026-10-14T17:45:00-04:00', recbto='2026-10-14T17:45:20-04:00'))
