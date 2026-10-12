<template>
  <div class="main-content">
    <breadcumb :page="$t('CustomersReport')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <!-- Date Range Filter & Quick Ranges -->
    <b-row class="mb-3 align-items-center justify-content-center" v-if="!isLoading">
      <b-col md="auto" class="text-center mb-2">
        <b-button-group size="sm">
          <b-button
            :variant="activeRange === 'this_month' ? 'primary' : 'outline-primary'"
            @click="setQuickRange('this_month')"
          >
            {{ $t('ThisMonth') || 'This Month' }}
          </b-button>
          <b-button
            :variant="activeRange === 'last_month' ? 'primary' : 'outline-primary'"
            @click="setQuickRange('last_month')"
          >
            {{ $t('LastMonth') || 'Last Month' }}
          </b-button>
          <b-button
            :variant="activeRange === 'this_year' ? 'primary' : 'outline-primary'"
            @click="setQuickRange('this_year')"
          >
            {{ $t('ThisYear') || 'This Year' }}
          </b-button>
          <b-button
            :variant="activeRange === 'all' ? 'primary' : 'outline-primary'"
            @click="setQuickRange('all')"
          >
            {{ $t('AllTime') || 'All Time' }}
          </b-button>
        </b-button-group>
      </b-col>
      <b-col md="auto" class="text-center mb-2">
        <date-range-picker
          v-model="dateRange"
          :startDate="startDate"
          :endDate="endDate"
          :time-picker="false"
          :autoApply="true"
          :showDropdowns="true"
          :opens="'right'"
          :drops="'down'"
          @update="Submit_filter_dateRange"
          :locale-data="locale"
        >
          <template v-slot:input="picker" style="min-width: 250px;">
            {{ formatDateTime(picker.startDate) }} — {{ formatDateTime(picker.endDate) }}
          </template>
        </date-range-picker>
      </b-col>
    </b-row>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
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
          <b-button @click="Customers_PDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
        </div>
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a title="PDF" class="cursor-pointer" v-b-tooltip.hover @click="Download_PDF(props.row , props.row.id)">
              <lucide-icon class="text-25 text-success" name="copy" />
            </a>
            <router-link title="Report" :to="'/app/reports/detail_customer/'+props.row.id">
             <lucide-icon class="text-25 text-info" name="eye" />
            </router-link>
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
import { mapActions, mapGetters } from "vuex";
import DateRangePicker from "vue2-daterange-picker";
import "vue2-daterange-picker/dist/vue2-daterange-picker.css";
import moment from "moment";

export default {
  metaInfo: {
    title: "Report Customers"
  },
  components: { DateRangePicker },
  data() {
    const startOfMonth = moment().startOf("month");
    const endOfMonth = moment().endOf("month");
    return {
      isLoading: true,
      activeRange: "this_month",
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
      startDate: startOfMonth.format("YYYY-MM-DD"),
      endDate: endOfMonth.format("YYYY-MM-DD"),
      dateRange: {
        startDate: startOfMonth.toDate(),
        endDate: endOfMonth.toDate()
      },
      locale: {
        Label: "Apply",
        cancelLabel: "Cancel",
        weekLabel: "W",
        customRangeLabel: "Custom Range",
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),
        firstDay: 1,
      },
      clients: [],
      client: {},
      rows: [{
        name: "Total",
        children: [],
      }],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    columns() {
      return [
        {
          label: this.$t("CustomerName"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Phone"),
          field: "phone",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("TotalSales"),
          field: "total_sales",
          type: "decimal",
          headerField: this.sumTotalSales,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Amount"),
          field: "total_amount",
          type: "decimal",
          headerField: this.sumCount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Paid"),
          field: "total_paid",
          type: "decimal",
          headerField: this.sumCount2,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Sale_Due"),
          field: "due",
          type: "decimal",
          headerField: this.sumCount3,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Sell_Return_Due"),
          field: "return_Due",
          type: "decimal",
          headerField: this.sumCount4,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Action"),
          field: "actions",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        }
      ];
    }
  },

  methods: {
    sumTotalSales(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (parseInt(r.total_sales, 10) || 0), 0);
    },

    sumCount(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (parseFloat(r.total_amount) || 0), 0);
    },

    sumCount2(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (parseFloat(r.total_paid) || 0), 0);
    },

    sumCount3(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (parseFloat(r.due) || 0), 0);
    },

    sumCount4(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) return 0;
      return rowObj.children.reduce((s, r) => s + (parseFloat(r.return_Due) || 0), 0);
    },

    setQuickRange(type) {
      this.activeRange = type;
      if (type === "this_month") {
        const start = moment().startOf("month");
        const end = moment().endOf("month");
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate = end.format("YYYY-MM-DD");
        this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      } else if (type === "last_month") {
        const start = moment().subtract(1, "month").startOf("month");
        const end = moment().subtract(1, "month").endOf("month");
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate = end.format("YYYY-MM-DD");
        this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      } else if (type === "this_year") {
        const start = moment().startOf("year");
        const end = moment().endOf("year");
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate = end.format("YYYY-MM-DD");
        this.dateRange = { startDate: start.toDate(), endDate: end.toDate() };
      } else if (type === "all") {
        this.startDate = "";
        this.endDate = "";
        this.dateRange = { startDate: "", endDate: "" };
      }
      this.Get_Client_Report(1);
    },

    //--------------------------- Customers_PDF (All Customers) -------------------------------\\
    Customers_PDF() {
      const self = this;
      NProgress.start();
      NProgress.set(0.1);

      // Fetch all matching records with limit: -1
      axios
        .get("report/client", {
          params: {
            page: 1,
            SortField: self.serverParams.sort.field,
            SortType: self.serverParams.sort.type,
            search: self.search,
            limit: -1,
            from: self.startDate || "",
            to: self.endDate || ""
          }
        })
        .then(response => {
          NProgress.done();
          const clientsData = response.data.report || [];

          const pdf = new jsPDF("l", "pt");
          try {
            pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "normal");
            pdf.addFont("/fonts/Vazirmatn-Bold.ttf", "Vazirmatn", "bold");
          } catch (e) {}
          pdf.setFont("Vazirmatn", "normal");

          const marginX = 40;
          const rtl = (self.$i18n && ["ar", "fa", "ur", "he"].includes(self.$i18n.locale)) ||
                      (typeof document !== "undefined" && document.documentElement.dir === "rtl");

          const filterItems = [];
          if (self.startDate && self.endDate) {
            filterItems.push({ label: "Date Range", value: `${self.startDate}  —  ${self.endDate}` });
          }
          if (self.search) {
            filterItems.push({ label: "Search", value: self.search });
          }
          const hasFilters = filterItems.length > 0;

          const body = clientsData.map(r => [
            r.name || "",
            r.phone || "",
            r.total_sales || 0,
            parseFloat(r.total_amount || 0).toFixed(2),
            parseFloat(r.total_paid || 0).toFixed(2),
            parseFloat(r.due || 0).toFixed(2),
            parseFloat(r.return_Due || 0).toFixed(2),
          ]);

          const totalSalesCount = clientsData.reduce((s, r) => s + (parseInt(r.total_sales, 10) || 0), 0);
          const totalAmount = clientsData.reduce((s, r) => s + (parseFloat(r.total_amount) || 0), 0);
          const totalPaid = clientsData.reduce((s, r) => s + (parseFloat(r.total_paid) || 0), 0);
          const totalDue = clientsData.reduce((s, r) => s + (parseFloat(r.due) || 0), 0);
          const totalReturnDue = clientsData.reduce((s, r) => s + (parseFloat(r.return_Due) || 0), 0);

          const foot = [[
            self.$t("Total") || "Total",
            "",
            totalSalesCount,
            totalAmount.toFixed(2),
            totalPaid.toFixed(2),
            totalDue.toFixed(2),
            totalReturnDue.toFixed(2),
          ]];

          const headers = [
            self.$t("CustomerName"),
            self.$t("Phone"),
            self.$t("TotalSales"),
            self.$t("Amount"),
            self.$t("Paid"),
            self.$t("Total_Sale_Due"),
            self.$t("Total_Sell_Return_Due"),
          ];

          autoTable(pdf, {
            head: [headers],
            body,
            foot,
            startY: hasFilters ? 125 : 90,
            theme: "striped",
            margin: { left: marginX, right: marginX },
            styles:             { font: "Vazirmatn", fontSize: 8, cellPadding: 4, halign: rtl ? "right" : "left", textColor: 33 },
            headStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
            alternateRowStyles: { fillColor: [245, 247, 250] },
            footStyles:         { font: "Vazirmatn", fontStyle: "bold", fillColor: [26, 86, 219], textColor: 255 },
            didDrawPage(d) {
              const pageW = pdf.internal.pageSize.getWidth();
              const pageH = pdf.internal.pageSize.getHeight();

              // Blue banner
              pdf.setFillColor(26, 86, 219);
              pdf.rect(0, 0, pageW, 60, "F");

              // Title
              pdf.setTextColor(255);
              pdf.setFont("Vazirmatn", "bold");
              pdf.setFontSize(16);
              const title = self.$t("CustomersReport") || "Customers Sales Report";
              rtl ? pdf.text(title, pageW - marginX, 38, { align: "right" })
                  : pdf.text(title, marginX, 38);

              // Subtitle in banner
              if (self.startDate && self.endDate) {
                pdf.setFontSize(9);
                pdf.setFont("Vazirmatn", "normal");
                const sub = `${self.startDate}  —  ${self.endDate}`;
                rtl ? pdf.text(sub, pageW - marginX, 52, { align: "right" })
                    : pdf.text(sub, marginX, 52);
              }
              pdf.setTextColor(33);

              // Filter summary box
              if (hasFilters) {
                const lineH = 13;
                const maxW = pageW - 2 * marginX;
                const filterText = filterItems.map(f => `${f.label}: ${f.value}`).join("   |   ");
                const lines = pdf.splitTextToSize(filterText, maxW).slice(0, 3);
                const boxH = 8 + lines.length * lineH + 6;

                pdf.setFillColor(235, 242, 255);
                pdf.rect(0, 62, pageW, boxH, "F");
                pdf.setFont("Vazirmatn", "bold");
                pdf.setFontSize(7);
                pdf.setTextColor(26, 86, 219);
                rtl ? pdf.text("Applied Filters:", pageW - marginX, 72, { align: "right" })
                    : pdf.text("Applied Filters:", marginX, 72);
                pdf.setFont("Vazirmatn", "normal");
                pdf.setFontSize(8.5);
                pdf.setTextColor(50, 50, 50);
                lines.forEach((line, i) => {
                  rtl ? pdf.text(line, pageW - marginX, 83 + i * lineH, { align: "right" })
                      : pdf.text(line, marginX, 83 + i * lineH);
                });
                pdf.setTextColor(33);
              }

              // Page numbers
              pdf.setFontSize(8);
              pdf.setTextColor(100);
              const pn = `${d.pageNumber} / ${pdf.internal.getNumberOfPages()}`;
              rtl ? pdf.text(pn, marginX, pageH - 14, { align: "left" })
                  : pdf.text(pn, pageW - marginX, pageH - 14, { align: "right" });
            },
          });

          pdf.save("Customers_Report.pdf");
        })
        .catch(() => {
          NProgress.done();
        });
    },

    //--------------------------- Download_PDF for single client -------------------------------\\
    Download_PDF(client, id) {
      NProgress.start();
      NProgress.set(0.1);
     
      axios
        .get("report/client_pdf/" + id, {
          responseType: "blob",
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "report-" + client.name + ".pdf");
          document.body.appendChild(link);
          link.click();
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          setTimeout(() => NProgress.done(), 500);
        });
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Client_Report(currentPage);
      }
    },

    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Client_Report(1);
      }
    },

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Client_Report(this.serverParams.page);
    },

    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Client_Report(this.serverParams.page);
    },

    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    printTableOnly() {
      const title = `${this.$t("Reports")} / ${this.$t("CustomersReport")}`;
      const clients = Array.isArray(this.rows[0]?.children) ? this.rows[0].children : [];
      
      let tableHTML = '<table style="width: 100%; border-collapse: collapse; font-size: 10px;">';
      tableHTML += '<thead><tr>';
      
      this.columns.filter(col => col.field !== 'actions').forEach(col => {
        tableHTML += `<th style="border: 1px solid #ddd; padding: 6px 8px; background-color: #f5f5f5; font-weight: bold; text-align: left;">${col.label}</th>`;
      });
      tableHTML += '</tr></thead><tbody>';
      
      clients.forEach(client => {
        tableHTML += '<tr>';
        this.columns.filter(col => col.field !== 'actions').forEach(col => {
          let cellValue = '';
          if (col.field === 'name') {
            cellValue = client.name || '';
          } else if (col.field === 'phone') {
            cellValue = client.phone || '';
          } else if (col.field === 'total_sales') {
            cellValue = client.total_sales || 0;
          } else if (col.field === 'total_amount' || col.field === 'total_paid' || col.field === 'due' || col.field === 'return_Due') {
            cellValue = this.formatNumber(client[col.field] || 0, 2);
          } else {
            cellValue = client[col.field] || '';
          }
          tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; text-align: left;">${cellValue}</td>`;
        });
        tableHTML += '</tr>';
      });
      tableHTML += '</tbody>';

      // Grand totals footer
      const totalSales = this.sumTotalSales(this.rows[0]);
      const totalAmount = this.sumCount(this.rows[0]);
      const totalPaid = this.sumCount2(this.rows[0]);
      const totalDue = this.sumCount3(this.rows[0]);
      const totalReturnDue = this.sumCount4(this.rows[0]);

      tableHTML += `<tfoot><tr>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${this.$t("Total") || "Total"}</td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px;"></td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${totalSales}</td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${this.formatNumber(totalAmount, 2)}</td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${this.formatNumber(totalPaid, 2)}</td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${this.formatNumber(totalDue, 2)}</td>`;
      tableHTML += `<td style="border: 1px solid #ddd; padding: 6px 8px; font-weight: bold; text-align: left;">${this.formatNumber(totalReturnDue, 2)}</td>`;
      tableHTML += `</tr></tfoot></table>`;

      const w = window.open("", "_blank");
      if (!w) {
        alert("Please allow popups to print");
        return;
      }

      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .map(l => l.outerHTML)
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
    <style>
      @media print { 
        body, body * { visibility: visible !important; }
        @page { size: A4 landscape; margin: 0.3cm; }
      }
      body { margin: 0.3cm; font-family: Arial, sans-serif; }
      .print-header { font-weight: 600; margin-bottom: 10px; font-size: 14px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
      th { background-color: #f5f5f5; font-weight: bold; }
      tr:nth-child(even) { background-color: #f9f9f9; }
      tfoot tr td { background-color: #f0f4f8; font-weight: bold; }
    </style>
  </head>
  <body>
    <div class="print-header">${title} ${this.startDate && this.endDate ? `(${this.startDate} — ${this.endDate})` : ''}</div>
    ${tableHTML}
  </body>
</html>`);
      doc.close();

      w.focus();
      setTimeout(() => {
        w.print();
        w.close();
      }, 400);
    },

    Submit_filter_dateRange() {
      const start = this.dateRange.startDate ? moment(this.dateRange.startDate) : null;
      const end   = this.dateRange.endDate   ? moment(this.dateRange.endDate)   : null;
      if (start && end) {
        this.startDate = start.format("YYYY-MM-DD");
        this.endDate   = end.format("YYYY-MM-DD");
        this.activeRange = "custom";
      }
      this.Get_Client_Report(1);
    },

    formatDateTime(date) {
      return date ? moment(date).format("YYYY-MM-DD") : "";
    },

    Get_Client_Report(page) {
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get("report/client", {
          params: {
            page: page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            from: this.startDate || "",
            to: this.endDate || ""
          }
        })
        .then(response => {
          this.clients = response.data.report || [];
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.clients;
          NProgress.done();
          this.isLoading = false;
        })
        .catch(() => {
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    }
  },

  created: function() {
    this.setQuickRange("this_month");
  }
};
</script>