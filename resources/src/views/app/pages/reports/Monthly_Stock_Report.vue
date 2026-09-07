<template>
  <div class="main-content">
    <breadcumb :page="$t('monthly_stock_report')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <div class="row align-items-end mb-3">
        <div class="col-md-3 mb-2">
          <label class="mb-1 d-block text-muted">{{$t('DateRange')}}</label>
          <date-range-picker
            v-model="dateRange"
            :locale-data="locale"
            :autoApply="true"
            :showDropdowns="true"
            :opens="'right'"
            :drops="'down'"
            :parentEl="'body'"
            :linkedCalendars="false"
            @update="onDateRangeUpdate"
          >
            <template v-slot:input="picker">
              <b-button variant="light" class="btn-pill">
                <lucide-icon class="mr-1" name="calendar-days" />
                {{ fmt(picker.startDate) }} - {{ fmt(picker.endDate) }}
              </b-button>
            </template>
          </date-range-picker>
        </div>

        <div class="col-md-3 mb-2">
          <b-form-group :label="$t('warehouse')">
            <v-select
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
            />
          </b-form-group>
        </div>

        <div class="col-md-2 mb-2">
          <b-button @click="Get_Monthly_Stock_Report(1)" variant="primary" size="sm" block>
            <lucide-icon name="filter" /> {{ $t('Filter') }}
          </b-button>
        </div>
        <div class="col-md-2 mb-2">
          <b-button @click="resetFilter" variant="danger" size="sm" block>
            <lucide-icon name="power" /> {{ $t('Reset') }}
          </b-button>
        </div>
      </div>

      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="rows"
        :group-options="{
          enabled: true,
          headerPosition: 'bottom',
        }"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
        :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >

       <div slot="table-actions" class="mt-2 mb-3">
        <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
          <lucide-icon name="printer" /> {{ $t("print") }}
        </b-button>
          <b-button @click="monthly_stock_report_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
           <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="reports"
              :columns="columns"
              :file-name="'monthly_stock_report'"
              :file-type="'xlsx'"
              :sheet-name="'monthly_stock_report'"
              >
              <lucide-icon name="file-spreadsheet" /> EXCEL
          </vue-excel-xlsx>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field === 'received'">
            {{ formatQuantity(props.row.received, props.row.unit) }}
          </span>
          <span v-else-if="props.column.field === 'sold'">
            {{ formatQuantity(props.row.sold, props.row.unit) }}
          </span>
          <span v-else-if="props.column.field === 'current_stock'">
            {{ formatQuantity(props.row.current_stock, props.row.unit) }}
          </span>
          <span v-else-if="props.column.field === 'stock_value_cost'">
            {{ formatPrice(props.row.stock_value_cost) }}
          </span>
          <span v-else-if="props.column.field === 'stock_value_selling'">
            {{ formatPrice(props.row.stock_value_selling) }}
          </span>
          <span v-else>
            {{ props.formattedRow[props.column.field] }}
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>


<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import autoTable from "jspdf-autotable";
import moment from "moment";
import DateRangePicker from 'vue2-daterange-picker'
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import { mapGetters } from "vuex";
import {
  formatPriceDisplay as formatPriceDisplayHelper,
  getPriceFormatSetting
} from "../../../../utils/priceFormat";

export default {
  metaInfo: {
    title: "Monthly Stock Report"
  },
  components: { 'date-range-picker': DateRangePicker },
  data() {
    const now = new Date();
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      search: "",
      totalRows: "",
      rows: [{ id: 0, children: [] }],
      warehouses: [],
      warehouse_id: "",
      dateRange: { startDate: startOfMonth, endDate: endOfMonth },
      // Optional price format key for frontend display (loaded from system settings/localStorage)
      price_format_key: null,
      locale: {
        Label: this.$t('Apply') || 'Apply',
        cancelLabel: this.$t('Cancel') || 'Cancel',
        weekLabel: 'W',
        customRangeLabel: this.$t('CustomRange') || 'Custom Range',
        daysOfWeek: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        monthNames: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        firstDay: 1,
      }
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    reports() {
      return this.rows[0].children;
    },
    columns() {
      return [
        {
          label: this.$t("ProductCode"),
          field: "code",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Name_product"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Categorie"),
          field: "category",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Stock_Received"),
          field: "received",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
          headerField: this.sumReceived
        },
        {
          label: this.$t("Stock_Sold"),
          field: "sold",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
          headerField: this.sumSold
        },
        {
          label: this.$t("Current_stock"),
          field: "current_stock",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
          headerField: this.sumCurrentStock
        },
        {
          label: `${this.$t("Stock_Value_Cost")} (${this.fmt(this.dateRange.endDate)})`,
          field: "stock_value_cost",
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false,
          headerField: this.sumStockValueCost
        },
        {
          label: `${this.$t("Stock_Value_Selling")} (${this.fmt(this.dateRange.endDate)})`,
          field: "stock_value_selling",
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false,
          headerField: this.sumStockValueSelling
        }
      ];
    }
  },

  methods: {

    //---- Format date for picker display and API params (YYYY-MM-DD)
    fmt(d) { try { return moment(d).format('YYYY-MM-DD') } catch(e) { return '' } },

    onDateRangeUpdate() { this.Get_Monthly_Stock_Report(1) },

    //---- Format a quantity with its unit for display
    formatQuantity(number, unit) {
      const qty = parseFloat(number || 0);
      return unit ? `${qty} ${unit}` : `${qty}`;
    },

    //---- Group footer helpers for vue-good-table (totals row)
    sumReceived(rowObj) {
      return this.sumField(rowObj, "received");
    },
    sumSold(rowObj) {
      return this.sumField(rowObj, "sold");
    },
    sumCurrentStock(rowObj) {
      return this.sumField(rowObj, "current_stock");
    },
    sumField(rowObj, field) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatQuantity(0, "");
      }
      let sum = 0;
      let unit = "";
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i][field] || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
        if (!unit && rowObj.children[i].unit) {
          unit = rowObj.children[i].unit;
        }
      }
      return this.formatQuantity(sum, unit);
    },

    //---- Price formatting for display only (does NOT affect calculations or stored values)
    formatPriceDisplay(number, dec) {
      try {
        const decimals = Number.isInteger(dec) ? dec : 2;
        const n = Number(number || 0);
        const key = this.price_format_key || getPriceFormatSetting({ store: this.$store });
        if (key) {
          this.price_format_key = key;
        }
        const effectiveKey = key || null;
        return formatPriceDisplayHelper(n, decimals, effectiveKey);
      } catch (e) {
        const n = Number(number || 0);
        return n.toLocaleString(undefined, { maximumFractionDigits: dec || 2 });
      }
    },

    formatPrice(number) {
      const currency = (this.currentUser && this.currentUser.currency) || '';
      const value = this.formatPriceDisplay(number, 2);
      return currency ? `${currency} ${value}` : value;
    },

    //---- Group footer helpers for vue-good-table (money totals row)
    sumStockValueCost(rowObj) {
      return this.sumMoneyField(rowObj, "stock_value_cost");
    },
    sumStockValueSelling(rowObj) {
      return this.sumMoneyField(rowObj, "stock_value_selling");
    },
    sumMoneyField(rowObj, field) {
      if (!rowObj || !Array.isArray(rowObj.children)) {
        return this.formatPrice(0);
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        const value = parseFloat(rowObj.children[i][field] || 0);
        if (Number.isFinite(value)) {
          sum += value;
        }
      }
      return this.formatPrice(sum);
    },

     //----------------------------------- PDF ------------------------------\\
    monthly_stock_report_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      const fontPath = "/fonts/Vazirmatn-Bold.ttf";
      try {
        pdf.addFont(fontPath, "Vazirmatn", "normal");
        pdf.addFont(fontPath, "Vazirmatn", "bold");
      } catch(e) {}
      pdf.setFont("Vazirmatn", "normal");

      const headers = [
        self.$t("ProductCode"),
        self.$t("Name_product"),
        self.$t("Categorie"),
        self.$t("Stock_Received"),
        self.$t("Stock_Sold"),
        self.$t("Current_stock"),
        `${self.$t("Stock_Value_Cost")} (${self.fmt(self.dateRange.endDate)})`,
        `${self.$t("Stock_Value_Selling")} (${self.fmt(self.dateRange.endDate)})`
      ];

      const body = (self.reports || []).map(report => ([
        report.code,
        report.name,
        report.category,
        self.formatQuantity(report.received, report.unit),
        self.formatQuantity(report.sold, report.unit),
        self.formatQuantity(report.current_stock, report.unit),
        self.formatPrice(report.stock_value_cost),
        self.formatPrice(report.stock_value_selling)
      ]));

      const footer = [[
        self.$t("Total"),
        '', '',
        self.sumReceived(self.rows[0]),
        self.sumSold(self.rows[0]),
        self.sumCurrentStock(self.rows[0]),
        self.sumStockValueCost(self.rows[0]),
        self.sumStockValueSelling(self.rows[0])
      ]];

      const marginX = 40;
      const rtl =
        (self.$i18n && ['ar','fa','ur','he'].includes(self.$i18n.locale)) ||
        (typeof document !== 'undefined' && document.documentElement.dir === 'rtl');

      autoTable(pdf, {
        head: [headers],
        body: body,
        foot: footer,
        startY: 110,
        theme: 'striped',
        margin: { left: marginX, right: marginX },
        styles: { font: 'Vazirmatn', fontSize: 9, cellPadding: 4, halign: rtl ? 'right' : 'left', textColor: 33 },
        headStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255 },
        alternateRowStyles: { fillColor: [245,247,250] },
        footStyles: { font: 'Vazirmatn', fontStyle: 'bold', fillColor: [26,86,219], textColor: 255 },
        didDrawPage: (d) => {
          const pageW = pdf.internal.pageSize.getWidth();
          const pageH = pdf.internal.pageSize.getHeight();

          pdf.setFillColor(26,86,219);
          pdf.rect(0, 0, pageW, 60, 'F');

          pdf.setTextColor(255);
          pdf.setFont('Vazirmatn', 'bold');
          pdf.setFontSize(16);
          const title = `Monthly Stock Report - ${self.fmt(self.dateRange.startDate)} to ${self.fmt(self.dateRange.endDate)}`;
          rtl ? pdf.text(title, pageW - marginX, 38, { align: 'right' })
              : pdf.text(title, marginX, 38);

          pdf.setTextColor(33);

          pdf.setFontSize(8);
          const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
          rtl ? pdf.text(pn, marginX, pageH - 14, { align: 'left' })
              : pdf.text(pn, pageW - marginX, pageH - 14, { align: 'right' });
        }
      });

      pdf.save("Monthly_Stock_Report.pdf");

    },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Monthly_Stock_Report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Monthly_Stock_Report(1);
      }
    },

    //---- Event on Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Monthly_Stock_Report(this.serverParams.page);
    },

    //---- Event on Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Monthly_Stock_Report(this.serverParams.page);
    },

    //------ Print Table Only
    printTableOnly() {
      const root = this.$el;
      if (!root) {
        window.print();
        return;
      }

      const tableCard = root.querySelector(".print-table-only");
      if (!tableCard) {
        window.print();
        return;
      }

      const reportsData = this.reports || [];

      let tableHtml = `<table class="vgt-table table table-hover tableOne">`;

      tableHtml += `<thead><tr>`;
      this.columns.forEach(col => {
        tableHtml += `<th class="text-left">${col.label}</th>`;
      });
      tableHtml += `</tr></thead>`;

      tableHtml += `<tbody>`;
      reportsData.forEach(row => {
        tableHtml += `<tr>`;
        this.columns.forEach(col => {
          let cellContent;
          if (['received', 'sold', 'current_stock'].includes(col.field)) {
            cellContent = this.formatQuantity(row[col.field], row.unit);
          } else if (['stock_value_cost', 'stock_value_selling'].includes(col.field)) {
            cellContent = this.formatPrice(row[col.field]);
          } else {
            cellContent = row[col.field] || '';
          }
          tableHtml += `<td class="text-left">${cellContent}</td>`;
        });
        tableHtml += `</tr>`;
      });
      tableHtml += `</tbody>`;

      tableHtml += `<tfoot><tr>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.$t('Total')}</td>`;
      tableHtml += `<td colspan="2"></td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.sumReceived(this.rows[0])}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.sumSold(this.rows[0])}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.sumCurrentStock(this.rows[0])}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.sumStockValueCost(this.rows[0])}</td>`;
      tableHtml += `<td class="text-left font-weight-bold">${this.sumStockValueSelling(this.rows[0])}</td>`;
      tableHtml += `</tr></tfoot>`;

      tableHtml += `</table>`;

      const w = window.open("", "_blank");
      if (!w) {
        window.print();
        return;
      }

      const title = `${this.$t("Reports")} / ${this.$t("monthly_stock_report")} (${this.fmt(this.dateRange.startDate)} - ${this.fmt(this.dateRange.endDate)})`;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
        .join("\n");

      const inlineStyles = Array.from(document.querySelectorAll("style"))
        .filter(s => !((s.textContent || "").includes("@media print")))
        .map(s => s.outerHTML)
        .join("\n");

      const doc = w.document;
      doc.open();
      doc.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <base href="${window.location.origin}/" />
    <title>${title}</title>
    ${links}
    ${inlineStyles}
    <style>
      @media print { body, body * { visibility: visible !important; } }
      body { margin: 0.3cm; }
      .print-header { font-weight: 600; margin-bottom: 8px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
    </style>
  </head>
  <body>
    <div class="print-header">${title}</div>
    ${tableHtml}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

     //---------------------- Reset Filters ------------------------------\\
    resetFilter() {
      const now = new Date();
      this.dateRange = {
        startDate: new Date(now.getFullYear(), now.getMonth(), 1),
        endDate: new Date(now.getFullYear(), now.getMonth() + 1, 0)
      };
      this.warehouse_id = "";
      this.Get_Monthly_Stock_Report(1);
    },

    //--------------------------- Get Monthly Stock Report -------------\\

    Get_Monthly_Stock_Report(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "report/monthly_stock?page=" +
            page +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&warehouse_id=" +
            this.warehouse_id +
            "&from=" +
            this.fmt(this.dateRange.startDate) +
            "&to=" +
            this.fmt(this.dateRange.endDate) +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit
        )
        .then(response => {
          this.rows = [{ id: 0, children: response.data.report || [] }];
          this.totalRows = response.data.totalRows;
          this.warehouses = response.data.warehouses;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.Get_Monthly_Stock_Report(1);
  }
};
</script>
